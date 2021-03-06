General information
===================
* Contributors: [michaelsauter](https://github.com/michaelsauter)
* Tags: symfony2, mvc, options, form
* Requires at least: 3.5
* Stable tag: 1.1
* License: MIT

Orchestra is a solid plugin development base formed of Symfony2 components. It was originally created during a TripleTime project at [SitePoint](http://www.sitepoint.com)


Description
===========

Orchestra integrates Doctrine2 and some Symfony2 components into Wordpress to allow developers to easily build admin pages that exceed the built-in Wordpress functionality.

The basic architecture is as follows:
* Orchestra mainly has 3 building blocks (components): Doctrine (ORM), Form, Twig. These can form a foundation for the development of arbitary plugins.
* The components used to form these building blocks are included via Composer
* Orchestra wires the building blocks together and provides additional functionality like class loading as well as a basic router and a controller modelled after the Symfony2 controller.
* Orchestra is initialized during the "_admin_menu" hook
* Then, during the "admin_menu" hook, every plugin based on Orchestra calls the Framework class of Orchestra, which then decides if the calling plugin is active (e.g. the user requested this plugin in the backend). If so, it passes control back to the plugin by executing the correct controller and stores the rendered output. The output must be rendered and stored at this stage, because later on, the headers are already sent.
* The generated output is then retrieved inside the callback of e.g. "add_meu_page".


Installation
============

1. Put `orchestra` into the `/wp-content/plugins/` directory
2. Run `composer install` (requires composer to be installed globally)
3. (Optionally) edit `config.php` to match your setup
4. Activate the plugin through the 'Plugins' menu in WordPress


General Usage
=============

1. Go to `wp-content/plugins/orchestra`
2. Execute `./console plugin:create your-plugin-name-here`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. While you're developing, make sure to set `WP_DEBUG` in `wp-config.php` to `true` in order to have the caches rewritten automatically


Interacting With the Database
=============================
Orchestra interacts with the database via Doctrine2. It also supports multisite setup out-of-the-box. When creating entities, you should specify the table name explicitly, but leave out the prefix as this is determined by Orchestra.
To create or modify the database schema, you need to provide install / update routines in your plugin and run SQL from there. Unfortunately, the Doctrine CLI tools are not supported. That being said, it is often more comfortable to handle schema changes upon install/update anyway.


Writing Tests
=============
You can write unit tests just like for every other PHP project. Orchestra uses PHPUnit to test its code. If you want to test your plugin with PHPUnit, take a look inside `tests/` to get started.


Deployment
==========
The repository intentionally does not contain the vendors. The vendor directory is configured by default to be `wp-content/vendors/orchestra`. Exclude this folder from version control if you want to install the dependencies via composer upon install. If you want to manage the dependencies locally however, commit the folder to your repository.


Frequently Asked Questions
==========================

Can I use other Composer libraries, too?
----------------------------------------

Yes, it is very easy to do so inside your plugin. You just need to  install Composer, create a composer.json file with your dependencies, install them and pass the namespaces to register as a 3rd parameter to the Framework::setupPlugin() call.

What Coding Style Guidelines to use?
------------------------------------

I recommend following the [Symfony2 Coding Standards](http://symfony.com/doc/2.0/contributing/code/standards.html) instead of the Wordpress Coding Guidelines. Orchestra itself and the templates use the Symfony2 Coding Standards.


Changelog
=========

1.1
---
* Removed Doctrine Migrations
* Fixed unit tests
* Updated readme

1.0
---
* Initial release