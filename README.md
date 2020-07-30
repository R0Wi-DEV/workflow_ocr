
# Nextcloud Workflow OCR app

## Table of contents
  * [Setup](#setup)
    + [App installation](#app-installation)
    + [Backend](#backend)
  * [Usage](#usage)
  * [How it works](#how-it-works)
    + [General](#general)
    + [PDF](#pdf)
  * [Development](#development)
    + [Dev setup](#dev-setup)
    + [Adding a new `OcrProcessor`](#adding-a-new--ocrprocessor-)
  * [Limitations](#limitations)
  * [Used libraries & components](#used-libraries---components)


## Setup
### App installation
First download and install the Nextcloud Workflow OCR app from the official Nexcloud-appstore or by downloading the appropriate tarball from the [releases](https://github.com/R0Wi/nextcloud_workflow_ocr/releases) page. 
```
cd /var/www/<NEXTCLOUD_INSTALL>/apps
https://github.com/R0Wi/nextcloud_workflow_ocr/releases/download/<VERSION>/workflow_ocr.tar.gz
tar -xzvf workflow_ocr.tar.gz
rm workflow_ocr.tar.gz
```

### Backend
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

For the OCR part the commandlinetool `tesseract` is used. Make sure you have the library and appropriate languages installed. I recommend installing the packages from [PPA](https://github.com/tesseract-ocr/tessdoc/blob/master/Home.md) because they're newer than the official package-sources (i tested with `tesseract 4.1.1`). On Ubuntu 18.04 you might type the following for languages english and german:
```bash
# Install PPA
sudo add-apt-repository ppa:alex-p/tesseract-ocr
sudo apt-get update

# Install Tesseract and language-files
sudo apt-get install tesseract-ocr tesseract-ocr-deu tesseract-ocr-eng
```
You can read more about the installation of `tesseract` [here](https://github.com/tesseract-ocr/tesseract/wiki).

## Usage
You can configure the OCR processing via Nextcloud's workflow engine. Therefore configure a new flow via `Settings -> Flow -> Add new flow` (if you don't see `OCR file` here the app isn't installed properly or you forgot to activate it).
![Usage setup](doc/img/usage_1.jpg "Usage")

A typical setup for processing incoming PDF-files and adding a text-layer to them might look like this:
![PDF setup](doc/img/usual_config_1.jpg "PDF setup")

## How it works
### General
Documentation will be added soon.
### PDF
Documentation will be added soon.

## Development
### Dev setup
Tools and packages you need for development:
* `make`
* [`composer`](https://getcomposer.org/download/) (Will be automatically installed when running `make build`)
* Properly setup `php`-environment
* Webserver (like Apache)

You can then build and install the app by cloning this repository into the Nextcloud apps folder and running `make build`.
```bash
cd /var/www/<NEXTCLOUD_INSTALL>/apps
git clone https://github.com/R0Wi/nextcloud_workflow_ocr.git
cd workflow_ocr
make build
```
Don't forget to activate the app via Nextcloud web-gui.

### Adding a new `OcrProcessor`
Documentation will be added soon.

## Limitations
* **Currently only pdf documents (`application/pdf`) can be used as input.** Other mimetypes are currently ignored but might be added in the future.
* Pdf metadata (like author, comments, ...) is not available in the converted output pdf document.
* Currently files are only processed based on workflow-events so there is no batch-mechanism for applying OCR to already existing files. This is a feature which might be added in the future.

## Used libraries & components
| Name | Version | Link |
|---|---|---|
| tesseract_ocr | >= 2.9 | https://github.com/thiagoalessio/tesseract-ocr-for-php |
| tesseract (commandline) | >= 4.0 | https://github.com/tesseract-ocr/tesseract |
| pdfparser | >= 0.15.0 | https://www.pdfparser.org/ |
| fpdi | >= 2.3 | https://www.setasign.com/products/fpdi/about/ |
| fpdf | >= 1.8 | http://www.fpdf.org/ |
| imagick php extension | >=2 | https://www.php.net/manual/de/book.imagick.php |
