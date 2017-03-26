# Moodle Repository Plugin `owncloud`

[![Build Status](https://travis-ci.org/pssl16/moodle-repository_owncloud.svg?branch=master)](https://travis-ci.org/pssl16/moodle-repository_owncloud)
[![codecov](https://codecov.io/gh/pssl16/moodle-repository_owncloud/branch/master/graph/badge.svg)](https://codecov.io/gh/pssl16/moodle-repository_owncloud)

# English

This plugin is depending on the [`oauth2owncloud` plugin](https://github.com/pssl16/moodle-tool_oauth2owncloud) and can not be used separately.

Created by the project seminar sciebo@Learnweb of the University of Münster.

## Installation

Copy the content of this repository to `repository/owncloud`.

## Admin Settings

Firstly, please ensure that the `oauth2owncloud` plugin is configured correctly. Otherwise this plugin will not work. Repository Plugins are activated under `Site-Administration ► Plugins ► Repositories`.

## User View

This plugin is available in all Activities where the file picker is used. However, course admins can disable it under `Course Administration ► Repositories`. The usage of this plugin can not be limited to specific user groups.

In the file picker a login button is displayed (assuming that the user is not authenticated yet):

![File picker login](pix/filepickerlogin.png)

When the button is clicked a pop-up window or a new tab is opened and the user will be requested to login and authorize Moodle. If authorization is granted, the user sees a tabular listing of the files available:

![File picker](pix/FilePickerredblock.png)

The first icon in the red block is used to reload the content. The second button can be used to logout. The third button is only available for admins and redirects to the settings in the `oauth2owncloud` plugin.

Additional information can be found in our [documentation](https://pssl16.github.io).

# Deutsch

Dieses Plugin hängt vom [`oauth2owncloud` Plugin](https://github.com/pssl16/moodle-tool_oauth2owncloud) ab und kann nicht separat davon verwendet werden.

Erstellt vom Projektseminar sciebo@Learnweb der Westfälischen Wilhelms-Universität Münster.

## Installation

Kopieren Sie den Inhalt dieses Repositorys nach `repository/owncloud`.

## Admin Einstellungen

Bitte stellen Sie zuerst sicher, dass das `oauth2owncloud` Plugin korrekt konfiguriert ist. Sonst wird dieses Plugin nicht funktionieren. Repository Plugins werden unter `Site-Administration ► Plugins ► Repositories` aktiviert.

## Sicht des Nutzers

Dieses Plugin ist in allen Aktivitäten verfügbar, die die Dateiauswahl nutzen. Kurs Administratoren können es jedoch unter `Course Administration ► Repositories` deaktivieren. Die Benutzung dieses Plugins kann nicht auf bestimmte Nutzergruppen begrenzt werden.

In der Dateiauswahl wird ein Login-Button angezeigt (angenommen der Nutzer hat sich noch nicht authentifiziert):

![Dateiauswahl Login](pix/filepickerlogin.png)

Beim Klicken auf den Button wird ein Pop-up Fenster oder ein neuer Tab geöffnet und der Nutzer wird darum gebeten, Moodle zu autorisieren. Wenn die Autorisierung gewährt wurde, sieht der Nutzer eine tabellarische Auflistung der verfügbaren Dateien:

![Dateiauswahl](pix/FilePickerredblock.png)

Mit dem ersten Icon im roten Kasten kann der Inhalt neu geladen werden. Der zweite Button kann zum Abmelden verwendet werden. Der dritte Button ist nur für Administratoren verfügbar und leitet zu den Einstellungen des `oauth2owncloud` Plugins weiter.

Nähere Informationen finden Sie in unserer [Dokumentation](https://pssl16.github.io).
