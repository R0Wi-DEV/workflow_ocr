# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
# @author Bernhard Posselt <dev@bernhard-posselt.com>
# @copyright Bernhard Posselt 2016

# Generic Makefile for building and packaging a Nextcloud app which uses npm and
# Composer.
#
# Dependencies:
# * make
# * which
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive
# * npm: for building and testing everything JS
#
# If no composer.json is in the app root directory, the Composer step
# will be skipped. The same goes for the package.json which can be located in
# the app root or the js/ directory.
#
# The npm command by launches the npm build script:
#
#    npm run build
#
# The npm test command launches the npm test script:
#
#    npm run test
#
# The idea behind this is to be completely testing and build tool agnostic. All
# build tools and additional package managers should be installed locally in
# your project, since this won't pollute people's global namespace.
#
# The following npm scripts in your package.json install and update the bower
# and npm dependencies and use gulp as build system (notice how everything is
# run from the node_modules folder):
#
#    "scripts": {
#        "test": "node node_modules/gulp-cli/bin/gulp.js karma",
#        "prebuild": "npm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
#        "build": "node node_modules/gulp-cli/bin/gulp.js"
#    },

app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

all: build

# Fetches the PHP and JS dependencies and compiles the JS. If no composer.json
# is present, the composer step is skipped, if no package.json or js/package.json
# is present, the npm step is skipped
.PHONY: build
build:
ifneq (,$(wildcard $(CURDIR)/composer.json))
	make composer-build
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	make npm-install && make npm-build
endif

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
	php $(build_tools_directory)/composer.phar update --prefer-dist
else
	composer install --prefer-dist
endif

# Installs composer dependencies for building the app and
# wipes out unused composer files.
.PHONY: composer-build
composer-build:
	composer install --no-dev --prefer-dist

# Installs npm dependencies
.PHONY: npm-install
npm-install: check-npm
ifeq (,$(wildcard $(CURDIR)/package.json))
	cd js && npm run dev-install
else
	npm run dev-install
endif

# Runs npm build script
.PHONY: npm-build
npm-build: check-npm
ifeq (,$(wildcard $(CURDIR)/package.json))
	cd js && npm run build
else
	npm run build
endif

# Checks if npm is installed
.PHONY: check-npm
check-npm:
ifeq (,$(npm))
	$(error npm is not installed. Please install Node in version mentioned in package.json on your system)
endif

# Removes the appstore build
.PHONY: clean
clean:
	rm -rf ./build

# Same as clean but also removes dependencies installed by composer, bower and
# npm
.PHONY: distclean
distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

# Builds the source package
.PHONY: source
source:
	rm -rf $(source_build_directory)
	mkdir -p $(source_build_directory)
	tar cvzf $(source_package_name).tar.gz ../$(app_name) \
	--exclude-vcs \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/js/node_modules" \
	--exclude="../$(app_name)/node_modules" \
	--exclude="../$(app_name)/*.log" \
	--exclude="../$(app_name)/js/*.log" \

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	make distclean
	make build
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	tar cvzf $(appstore_package_name).tar.gz \
	--no-wildcards-match-slash \
	--exclude-vcs \
	--exclude="../$(app_name)/build" \
	--exclude="../$(app_name)/tests" \
	--exclude="../$(app_name)/Makefile" \
	--exclude="../$(app_name)/*.log" \
	--exclude="../$(app_name)/phpunit*xml" \
	--exclude="../$(app_name)/coverage*xml" \
	--exclude="../$(app_name)/composer.*" \
	--exclude="../$(app_name)/coverage_html" \
	--exclude="../$(app_name)/coverage" \
	--exclude="../$(app_name)/js/node_modules" \
	--exclude="../$(app_name)/js/tests" \
	--exclude="../$(app_name)/js/test" \
	--exclude="../$(app_name)/js/*.log" \
	--exclude="../$(app_name)/js/package.json" \
	--exclude="../$(app_name)/js/bower.json" \
	--exclude="../$(app_name)/js/karma.*" \
	--exclude="../$(app_name)/js/protractor.*" \
	--exclude="../$(app_name)/js/*.map" \
	--exclude="../$(app_name)/js/.*" \
	--exclude="../$(app_name)/package.json" \
	--exclude="../$(app_name)/bower.json" \
	--exclude="../$(app_name)/karma.*" \
	--exclude="../$(app_name)/protractor\.*" \
	--exclude="../$(app_name)/.*" \
	--exclude="../$(app_name)/src" \
	--exclude="../$(app_name)/node_modules" \
	--exclude="../$(app_name)/*.js" \
	--exclude="../$(app_name)/*.json" \
	--exclude="../$(app_name)/*.lock" \
	--exclude="../$(app_name)/*.cov" \
	--exclude="../$(app_name)/psalm.xml" \
	../$(app_name) \

.PHONY: php-test
php-test: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml

.PHONY: php-unittest
php-unittest: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml

.PHONY: php-integrationtest
php-integrationtest: composer
	$(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml

.PHONY: coverage-php
coverage-php:
	XDEBUG_MODE=coverage $(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.xml --coverage-php coverage/coverage_unittests.cov
	XDEBUG_MODE=coverage $(CURDIR)/vendor/phpunit/phpunit/phpunit -c phpunit.integration.xml --coverage-php coverage/coverage_integrationtests.cov
	XDEBUG_MODE=coverage $(CURDIR)/vendor/phpunit/phpcov/phpcov merge --clover ./coverage/php-coverage.xml ./coverage

.PHONY: html-coverage
html-coverage: composer coverage-php
	XDEBUG_MODE=coverage $(CURDIR)/vendor/phpunit/phpcov/phpcov merge --html coverage_html .

# Coverage PHP and JS + merge coverage
.PHONY: coverage-all
coverage-all: composer npm-install coverage-php js-test

.PHONY: lint
lint: composer npm-install
	composer run lint
	composer run cs:check
	npm run lint

.PHONY: lint-fix
lint-fix: composer npm-install
	composer run cs:fix
	npm run lint:fix

.PHONY: js-test
js-test: npm-install
	npm run test:unit

.PHONY: test
test: php-test js-test