
Yksinkertainen blogialusta: nettepuoti
======================================

Ensinnäkin,

* miksi nette*puoti*: koska tarkoitukseni oli kirjoittaa yksinkertainen verkkokauppa-alusta. (Siksi hinnat vielä pyörivät tietokannassa).
* miksi *nette*puoti: koska Nette oli erittäin kevyt järjestelmä, jonka ekosysteemi oli riittävä omiin tarpeisiin.

Mitä sisältää: 

* blogit voi kirjoittaa ja muokata markdown-formaatissa (ei WYSIWYGiä tai esikatselua erikseen)
* RSS-syötteiden tuottaminen
* OpenGraph-tiedot
* palautelomake
* kielet englanti/suomi
* käyttäjärekisteröinti
* oikeuksien hallinta (vain komentorivi)

Mitä ei sisällä:

* kuvien lisääminen verkkokäyttöliittymästä
* kaikenlainen muu hyödyllinen
* tekoäly

Kenelle:

* minulle

Mitä opin tämän tekemisestä:

* yhtä sun toista Nette-järjestelmästä
* yhtä sun toista SQLite-tietokantajärjestelmästä ja SQL:stä muutenkin
* Nette (ja PHP) -ekosysteemin eri cache-keinot
* kuinka lähettää hienoja HTML-sähköposteja
* OpenGraph- ja RSS-tietojen generointi
* monikielisen sivuston tekemisen 

Mitä kadun:

* Nette, vaikka onkin monin tavoin erinomainen, on API-dokumentaatioltaan varsin puutteellinen (vertaa esim. Symfony)
* Nette-ekosysteemin rajat

## Copyright Ilkka Forsblom



Alkuperäinen Netten mukana tullut readme alla:

Nette Web Project
=================

This is a simple, skeleton application using the [Nette](https://nette.org). This is meant to
be used as a starting point for your new projects.

[Nette](https://nette.org) is a popular tool for PHP web development.
It is designed to be the most usable and friendliest as possible. It focuses
on security and performance and is definitely one of the safest PHP frameworks.

If you like Nette, **[please make a donation now](https://nette.org/donate)**. Thank you!


Requirements
------------

- Web Project for Nette 3.1 requires PHP 8.0


Installation
------------

The best way to install Web Project is using Composer. If you don't have Composer yet,
download it following [the instructions](https://doc.nette.org/composer). Then use command:

	composer create-project nette/web-project path/to/install
	cd path/to/install


Make directories `temp/` and `log/` writable.


Web Server Setup
----------------

The simplest way to get started is to start the built-in PHP server in the root directory of your project:

	php -S localhost:8000 -t www

Then visit `http://localhost:8000` in your browser to see the welcome page.

For Apache or Nginx, setup a virtual host to point to the `www/` directory of the project and you
should be ready to go.

**It is CRITICAL that whole `app/`, `config/`, `log/` and `temp/` directories are not accessible directly
via a web browser. See [security warning](https://nette.org/security-warning).**
