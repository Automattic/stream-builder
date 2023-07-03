# PHPCS = PHP CodeSniffer

PHP CodeSniffer is a set of two PHP scripts; the main phpcs script that tokenizes PHP files to detect
violations of a defined coding standard, and a second phpcbf script to automatically correct coding standard violations.
PHP_CodeSniffer is an essential development tool that ensures your code remains clean and consistent.

This standard is a subset of the PSR1 & PSR2 standard, along with some PEAR standards. For information on the majority of common sniffs, [Click here](http://edorian.github.io/php-coding-standard-generator/#phpcs).

## Usage

To use PHPCS (assuming you have done `make install`) enter the following command into your terminal:

`vendor/bin/phpcs path/to/file.php`

This will run our standard set of rules against this file, (or directory). If any errors are found they will be logged to screen.

You can auto-fix this file using the `phpcbf` utility:

`vendor/bin/phpcbf path/to/file.php`

This will fix all errors that are fixable, things like spacing, braces placement, indentation, etc.

To see the list of rules that we are using, run the following command:

`vendor/bin/phpcs -e`

If you wish to find out what the sniff name that is causing the error, add `-s` to your command line arguments:

`vendor/bin/phpcs -s path/to/file.php`
