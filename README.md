
# Nextcloud Workflow OCR App

## Table of contents
   * [Nextcloud Workflow OCR App](#nextcloud-workflow-ocr-app)
      * [Setup](#setup)
         * [App installation](#app-installation)
            * [Backend Docker (recommended)](#backend-docker-recommended)
            * [Backend manual](#backend-manual)
         * [App settings](#app-settings)
      * [Usage](#usage)
      * [How it works](#how-it-works)
         * [General](#general)
         * [PDF](#pdf)
      * [Limitations](#limitations)
      * [Used libraries](#used-libraries)

## Setup
### App installation
First download and install the Nextcloud app (**TODO**). Alternatively you can install the app by cloning this repository into the Nextcloud apps folder and installing [composer](https://getcomposer.org/download/) dependencies.
```
cd /var/www/<NEXTCLOUD_INSTALL>/apps
git clone https://github.com/R0Wi/nextcloud_workflow_ocr.git
cd workflow_ocr
composer install
```
#### Backend Docker (recommended)
**---not implemented yet---**
#### Backend manual
Make sure `Imagick` is installed (the command below is for debian based Linux systems. It might be different on your system.).
```
sudo apt-get install php-imagick
```

Make sure `Imagick` is properly configured so that it can access pdf files. On debian based systems edit the configuration file `/etc/ImageMagick-6/policy.xml` (path might be different on your system). It has to contain at least this line:
```xml
<policymap>
  <!-- [...] -->
  <policy domain="coder" rights="read" pattern="PDF" />
  <!-- [...] -->
</policymap>

```
After editing the file you would usually need to restart your webserver so that the changes have an effect. Because the OCR processing is done in the background via cronjob that's not necessary in this case.

You can find additional information about `Imagick` [here](https://www.php.net/manual/en/imagick.setup.php).

For the OCR part the commandlinetool `tesseract` is used. Make sure you have the library and appropriate languages installed. I recommend installing the packages from [PPA](https://github.com/tesseract-ocr/tessdoc/blob/master/Home.md) because they're newer (i tested with `tesseract 4.1.1`). On debian based systems you might type the following for languages english and german:
```
sudo apt-get install tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng
```
You can read more about the installation of `tesseract` [here](https://github.com/tesseract-ocr/tesseract/wiki).

### App settings
Make sure the app settings are configured correctly depending on your backend configuration.

TODO Screenshot, how to reach menu...

## Usage
TODO

## How it works
### General
TODO
### PDF
TODO

## Limitations
* Pdf metadata (like author, comments, ...) is not available in the converted output pdf document
* Currently only pdf documents can be used as input


## Used libraries & components
| Name | Version | Link |
|---|---|---|
| tesseract_ocr | >= 2.9 | https://github.com/thiagoalessio/tesseract-ocr-for-php |
| tesseract (commandline) | >= 4.0 | https://github.com/tesseract-ocr/tesseract |
| pdfparser | >= 0.15.0 | https://www.pdfparser.org/ |
| fpdi | >= 2.3 | https://www.setasign.com/products/fpdi/about/ |
| fpdf | >= 1.8 | http://www.fpdf.org/ |
| imagick php extension | >=2 | https://www.php.net/manual/de/book.imagick.php |
