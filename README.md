# ExternalDatabaseAuth

A MediaWiki extension to authenticate users from an external MySQL/MariaDB database.

## Use Case / Purpose

This extension can be useful whenever you are running multiple web applications besides your MediaWiki and you are
keeping all user/login data in one central MySQL or MariaDB database. To avoid having to synchronize the accounts with
the MediaWiki database this extension simply connects to this external database, searches for the username that was
entered in the wiki's login form and - if it was found - checks, if the supplied password matches the one that is stored
alongside the username in the external database. If user and password match, a local user is created and authentication
is successful. If the local user already exists (due to a previous login) it is simply updated. Since no password is
stored in your wiki's database, authentication always relies on the external database.

This extension was inspired by [ExtAuthDB](https://www.mediawiki.org/wiki/Extension:ExtAuthDB) and tries to offer the
same functionality as a state of the art MediaWiki extension.

## Requirements

- MediaWiki 1.29.0 or above.
- An external MySQL or MariaDB database.
- A table containing usernames, hashed/encrypted passwords, email addresses and real names.

## Installation

- [Download](https://github.com/hochleitner/ExternalDatabaseAuth/releases) the latest release and place its contents into
a folder called `ExternalDatabaseAuth` in your wiki's `extensions/` folder.
- Alternatively, you can also clone this repository into a folder called `ExternalDatabaseAuth` in your wiki's `extensions/` folder.
Select the latest tag/release for the most stable version or simply clone HEAD for all the latest and greatest (which
might contain bugs though).
- Add the following code at the bottom of your `LocalSettings.php` and change the values to match your external database
structure:
```
wfLoadExtension( 'ExternalDatabaseAuth' );

$wgExternalDatabaseAuthDatabase = [
    'host'        => 'localhost',
    'user'        => 'yourdatabaseuser',
    'password'    => 'yourdatabasepassword',
    'database'    => 'databasename',
    'tablePrefix' => ''
];
$wgExternalDatabaseAuthFields = [
    'table'        => 'tablewithlogindata',
    'userLogin'    => 'fieldwithusernames',
    'userPassword' => 'fieldwithpasswords',
    'userEmail'    => 'fieldwithemailaddresses',
    'userRealName' => 'fieldwithrealname'
];
$wgExternalDatabaseAuthHash = 'bcrypt';
``` 

-  Navigate to `Special:Version` on your wiki to verify that the extension is successfully installed.

## Configuration 

The following three configuration variables are available:

- ``$wgExternalDatabaseAuthDatabase (array)``: Contains connection information to your external database. The
associative array has the following keys:
  - ``host``: The database host (default: localhost).
  - ``user``: The database user (default: false).
  - ``password``: The database password (default: false).
  - ``database``: The database name (default: false).
  - ``tablePrefix``: Prefix for database tables (default: empty string).
- ``$wgExternalDatabaseAuthFields (array)``: Contains information where and how the data in your external database is
stored. The associative array has the following keys:
  - ``table``: The name of the table containing the login data (default: false).
  - ``userLogin``: The field containing the login names (default: false).
  - ``userPassword``: The field containing the encrypted/hashed password for the user (default: false).
  - ``userEmail``: The field containing the user's email address (default: false).
  - ``userRealName``: The field containing the user's real name (default: false).
- ``$wgExternalDatabaseAuthHash (string, default: bcrypt)``: Specifies the algorithm that was used to encrypt/hash the
passwords in your external database (unencrypted passwords are not supported). The availability of algorithms largely depends on your PHP version. The following
values are supported (along with the required PHP version whenever it's higher than MediaWiki's
minimum requirement of 7.0.13):
  - bcrypt
  - argon2i (>= 7.2.0)
  - argon2id (>= 7.3.0)
  - md2
  - md4
  - md5
  - sha1
  - sha224
  - sha256
  - sha384
  - sha512
  - sha512/224 (>= 7.1.0)
  - sha512/256 (>= 7.1.0)
  - sha3-224 (>= 7.1.0)
  - sha3-256 (>= 7.1.0)
  - sha3-384 (>= 7.1.0)
  - sha3-512 (>= 7.1.0)
  - ripemd128
  - ripemd160
  - ripemd256
  - ripemd320
  - whirlpool
  - tiger128,3
  - tiger160,3
  - tiger192,3
  - tiger128,4
  - tiger160,4
  - tiger192,4
  - snefru
  - snefru256
  - gost
  - gost-crypto
  - adler32
  - crc32
  - crc32b
  - fnv132
  - fnv1a32
  - fnv164
  - fnv1a64
  - joaat
  - haval128,3
  - haval160,3
  - haval192,3
  - haval224,3
  - haval256,3
  - haval128,4
  - haval160,4
  - haval192,4
  - haval224,4
  - haval256,4
  - haval128,5
  - haval160,5
  - haval192,5
  - haval224,5
  - haval256,5

## Contributing

This extension was written out of personal need. The features as well as translations only cover the things that were
important for my scenario. I am also no regular MediaWiki or extension developer and gathered my knowledge from
documentation and other existing extensions.

In case you find this extension helpful, but you are missing a certain feature, feel free to create a
[pull request](https://github.com/hochleitner/ExternalDatabaseAuth/pulls) with your proposed changes. In case you are no
developer, please open an [issue](https://github.com/hochleitner/ExternalDatabaseAuth/issues) and describe what you
would like to see added/changed.

If you find any bugs or have suggestions on how to improve this extension from a technical perspective, please open a
PR or issue or contact me directly. The same goes for translations. If you'd like to see your language covered (although
there's only a few localized strings), send your translations via PR or issue.

### Developing

In case you want to add features yourself and propose them via PR, you can use the provided development tools for
maintaining code quality and MediaWiki coding standards through [Composer](https://getcomposer.org/).

First, install the development dependencies by calling

    composer install

To test your code and get a thorough report, call

    composer run-script test

To fix possible errors, invoke

    composer run-script fix