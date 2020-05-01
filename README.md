
# Nextcloud Workflow OCR App

## Setup
### App installation
First download and install the Nextcloud app (TODO). Alternatively you can install the app by cloning this repository into the Nextcloud apps folder and installing [composer](https://getcomposer.org/download/) dependencies.
```
cd /var/www/<NEXTCLOUD_INSTALL>/apps
git clone <URL> (TODO)
cd workflow_ocr
composer install
```
#### Backend Docker (recommended)
TODO :: not implemented yet
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

You can find additional information about `Imagick` [here](https://www.php.net/manual/en/imagick.setup.php).

### App settings
Make sure the app settings are configured correctly depending on your backend configuration.

TODO Screenshot, how to reach menu...

## Usage
TODO

