# Installing PDF Parser Library

The Paper Extraction system requires the `smalot/pdfparser` library to process PDF files.

## Installation

Run the following command in your terminal from the `backend_tution` directory:

```bash
cd F:\laragon\www\projects\uk\backend_tution
composer require smalot/pdfparser
```

## Alternative: Manual Installation

If Composer is not available or you prefer manual installation:

1. Edit `composer.json` in the `backend_tution` directory
2. Add the following to the `require` section:

```json
"require": {
    ...
    "smalot/pdfparser": "^2.0"
}
```

3. Run:

```bash
composer update smalot/pdfparser
```

## Verification

After installation, verify it's working by checking if the class exists:

```bash
php artisan tinker
```

Then in tinker:

```php
class_exists('Smalot\PdfParser\Parser');
// Should return: true
```

## Troubleshooting

### If you get "Class not found" error:

1. Make sure you've run `composer install` or `composer update`
2. Clear the autoload cache:
    ```bash
    composer dump-autoload
    ```
3. Restart your PHP server/Laravel application

### If Composer version is incompatible:

The library requires PHP 7.4+. If you're using PHP 8.1.4 (as shown in your error), you may need to:

-   Upgrade to PHP 8.2+ (as required by your Laravel version)
-   Or use an older version of the library compatible with PHP 8.1

## Note

The PDF parser is only needed for PDF file processing. If you're only processing Word documents, you can skip this installation (though Word processing is not yet fully implemented).
