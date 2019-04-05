<?php

namespace ExternalDatabaseAuth;

use BadMethodCallException;
use Config;
use ConfigException;
use InvalidArgumentException;
use MediaWiki\Auth\AbstractPasswordPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\PasswordAuthenticationRequest;
use MediaWiki\MediaWikiServices;
use StatusValue;
use stdClass;
use User;
use Wikimedia\Rdbms\Database;

class ExternalDatabaseAuth extends AbstractPasswordPrimaryAuthenticationProvider {
	/**
	 * Holds the configuration for the extension. Default values come from extension.json and can be
	 * overridden by variables set in LocalConfig.php.
	 *
	 * @var Config
	 */
	private $edaConfig;

	/**
	 * The complete set of user data retrieved from the external database. Contains at least the
	 * login name, password, email address and real name but probably more (depending on the
	 * structure of the external database).
	 *
	 * @var stdClass
	 */
	private $loginUser;

	/**
	 * Creates a new object for external database authentication. Queries the current configuration
	 * and stores it for further use.
	 *
	 * @param array $params Settings for this authentication provider.1
	 * @throws ConfigException If an error retrieving the configuration happens.
	 */
	public function __construct( array $params = [] ) {
		parent::__construct( $params );

		$this->edaConfig =
			MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( "ExternalDatabaseAuth" );
	}

	/**
	 * Connects to the external database with the specified connection information. Possible
	 * connection errors will be handled through MediaWiki's core an will in most cases result in an
	 * unrecoverable error.
	 *
	 * @return Database|null Returns the database object or null, if not successful.
	 * @throws ConfigException If an error retrieving the configuration happens.
	 */
	private function connectToDatabase() {
		$databaseParameters = $this->edaConfig->get( "ExternalDatabaseAuthDatabase" );

		return Database::factory( "mysql", [
			"host" => $databaseParameters["host"],
			"user" => $databaseParameters["user"],
			"password" => $databaseParameters["password"],
			"dbname" => $databaseParameters["database"],
			"flags" => 0,
			"tablePrefix" => $databaseParameters["tablePrefix"],
		] );
	}

	/**
	 * Start an authentication flow.
	 *
	 * @param AuthenticationRequest[] $reqs Contains data about the request.
	 *
	 * @return AuthenticationResponse Expected responses:
	 *  - PASS: The user is authenticated. Secondary providers will now run.
	 *  - FAIL: The user is not authenticated. Fail the authentication process.
	 *  - ABSTAIN: These $reqs are not handled. Some other primary provider may handle it.
	 *  - UI: The $reqs are accepted, no other primary provider will run.
	 *    Additional AuthenticationRequests are needed to complete the process.
	 *  - REDIRECT: The $reqs are accepted, no other primary provider will run.
	 *    Redirection to a third party is needed to complete the process.
	 * @throws ConfigException If an error retrieving the configuration happens.
	 */
	public function beginPrimaryAuthentication( array $reqs ) {
		/** @var PasswordAuthenticationRequest $req */
		$req =
			AuthenticationRequest::getRequestByClass( $reqs, PasswordAuthenticationRequest::class );

		// If there's no PasswordAuthenticationRequest, return ABSTAIN
		if ( !$req ) {
			return AuthenticationResponse::newAbstain();
		}

		// If username or password are empty, return ABSTAIN
		if ( $req->username === null || $req->password === null ) {
			return AuthenticationResponse::newAbstain();
		}

		// Connect to the external database
		$database = $this->connectToDatabase();

		// Get the field names necessary for the SQL query
		$fields = $this->edaConfig->get( "ExternalDatabaseAuthFields" );

		// Retrieve the whole record for the user that has been entered in the login form
		$this->loginUser =
			$database->selectRow( $fields["table"], [ "*" ],
				[ $fields["userLogin"] => $req->username ], __METHOD__ );

		// Authenticate the user (see if password matches the stored one)
		if ( $this->loginUser && $this->isPasswordValid( $req->password,
				$this->loginUser->{$fields["userPassword"]} ) ) {
			return AuthenticationResponse::newPass( $req->username );
		}

		// If authentication was not successful, return ABSTAIN so other providers can run
		return AuthenticationResponse::newAbstain();
	}

	/**
	 * Checks, if the supplied password matches the one stored in the external database. This method
	 * will either use PHP's password_verify() method if the hashing algorithm was set to "bcrypt",
	 * "argon2i" or "argon2i" or use PHP's hash() function for other popular hashing algorithms
	 * (such as sha256).
	 * The default algorithm is "bcrypt", to override this, supply a value for
	 * $wgExternalDatabaseAuthHash in LocalSettings.php (e.g.
	 * $wgExternalDatabaseAuthHash = "sha256"). Valid values are the ones that are also accepted by
	 * the hash() function (see PHP manual for details).
	 *
	 * @param string $enteredPassword The password entered by the user. Usually retrieved from the
	 * request object.
	 * @param string $storedPassword The password stored in the external database. Usually retrieved
	 * through a database query in the external database.
	 * @return bool Returns true if passwords match, otherwise false.
	 * @throws ConfigException If an error retrieving the configuration happens.
	 * @throws InvalidArgumentException If a hashing algorithm is supplied that is not supported.
	 */
	private function isPasswordValid( $enteredPassword, $storedPassword ) {
		// Create an array of all the hashing algorithms we can handle (by the hash() function) and
		// add the password_verify() algorithms as well
		$availableAlgos = hash_algos();
		array_push( $availableAlgos, "bcrypt", "argon2i", "argon2id" );

		// Retrieve the algorithm set by the user in LocalSettings.php (or the default value from
		// extension.json).
		$algo = $this->edaConfig->get( "ExternalDatabaseAuthHash" );

		// If the supplied algorithm is not in our list, we'll throw an Exception
		if ( !in_array( $algo, $availableAlgos ) ) {
			throw new InvalidArgumentException( wfMessage( "externaldatabaseauth-unsupported-algorithm" )
				->params( $algo )
				->text() );
		}

		switch ( $algo ) {
			// First the password_verify() algorithms
			case "bcrypt":
			case "argon2i":
			case "argon2id":
				return password_verify( $enteredPassword, $storedPassword );
			// Then all the ones covered by hash()
			default:
				$hashedPassword = hash( $algo, $enteredPassword );

				return hash_equals( $storedPassword, $hashedPassword );
		}
	}

	/**
	 * Performs tasks on the user after successful login. Since MediaWiki automatically creates a
	 * local user in its database upon successful authentication, this method adds the real name as
	 * well as the current email address to this user and updates the timestamp for email
	 * authentication. This is so that the local user is always in sync with the one from the
	 * external database. Be advised that the local user does not store the password hash so
	 * authentication will always rely on the external database (in order to avoid inconsistencies).
	 *
	 * @param User $user The user that has just performed the authentication process.
	 * @param AuthenticationResponse $response Data about the response (e.g. status such as PASS or
	 * FAIL).
	 * @throws ConfigException If an error retrieving the configuration happens.
	 */
	public function postAuthentication( $user, AuthenticationResponse $response ) {
		parent::postAuthentication( $user, $response );

		if ( $response->status === AuthenticationResponse::PASS ) {
			$fields = $this->edaConfig->get( "ExternalDatabaseAuthFields" );

			$user->setRealName( $this->loginUser->{$fields["userRealName"]} );
			$user->setEmail( $this->loginUser->{$fields["userEmail"]} );
			$user->setEmailAuthenticationTimestamp( wfTimestampNow() );
			$user->saveSettings();
		}
	}

	/**
	 * Test whether the named user exists. This method is not supported since it is not relevant if
	 * the user exists or not. If it doesn't exist it will be created if it exists it will be
	 * updated.
	 *
	 * @param string $username MediaWiki username.
	 * @param int $flags Bit field of User:READ_* constants.
	 * @return void Returns nothing.
	 * @throws BadMethodCallException Thrown since this method call is not supported.
	 */
	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		throw new BadMethodCallException( wfMessage( "externaldatabaseauth-no-user-testing" )->text() );
	}

	/**
	 * Validate a change of authentication data (e.g. passwords). This is not supported, so
	 * StatusValue::newGood("ignored") will always be returned.
	 *
	 * @param AuthenticationRequest $req The request.
	 * @param bool $checkData If false, $req hasn't been loaded from the submission so checks on
	 * user-submitted fields should be skipped. $req->username is considered user-submitted for this
	 * purpose, even if it cannot be changed via $req->loadFromSubmission.
	 * @return StatusValue Always StatusValue::newGood("ignored").
	 */
	public function providerAllowsAuthenticationDataChange(
		AuthenticationRequest $req, $checkData = true
	) {
		return StatusValue::newGood( "ignored" );
	}

	/**
	 * Change or remove authentication data (e.g. passwords). Not implemented.
	 *
	 * @param AuthenticationRequest $req The request data.
	 */
	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
		// Not implemented.
	}

	/**
	 * Fetch the account-creation type. Not implemented.
	 *
	 * @return void Returns nothing.
	 */
	public function accountCreationType() {
		// Not implemented.
	}

	/**
	 * Start an account creation flow. Not supported for this provider.
	 *
	 * @param User $user User being created (not added to the database yet). This may become a
	 * "UserValue" in the future, or User may be refactored into such.
	 * @param User $creator User doing the creation. This may become a "UserValue" in the future, or
	 * User may be refactored into such.
	 * @param AuthenticationRequest[] $reqs The request data.
	 * @return void Returns nothing.
	 * @throws BadMethodCallException Thrown since this method call is not supported.
	 */
	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		throw new BadMethodCallException(
			wfMessage( "externaldatabaseauth-no-explicit-account-creation" )->text() );
	}
}
