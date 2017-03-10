# moodle-repository_sciebo *(beta_candidate)*
# English
[![Build Status](https://travis-ci.org/pssl16/moodle-repository_sciebo.svg?branch=master)]
(https://travis-ci.org/pssl16/moodle-repository_sciebo)</br>
This Repository connects ownCloud with Moodle.
This Plugin currently works with WebDAV. It will later become a subplugin of [OAuth2Sciebo Plugin](https://github.com/pssl16/moodle-tool_oauth2sciebo).
Written and maintained by
[ProjectsSeminar of the University of Muenster](https://github.com/pssl16).

## Installation
This Plugin should go into `repository/owncloud`.

### Admin Settings
Please ensure that all necessary settings are filled in the `admin_tool_oauth2owncloud` Plugin.
Otherwise the Plugin will not work. Repositorys Plugins have to be activated from the side administrator
in `Site-Administration>Plugins>Repositories`. 
The admin can change the name of the plugin instance in the settings. This name is used globally for 
all plugin instances.

### User View
The repository is available in cours and private context, and has not to be activated by e.g. the course manager.
However course admins can delete the repository in the path `Course Administartion>repositories`
The usage of the plugin can not be limited to specific user groups.
In the File Picker a Login Button is displayed (assumed that the user is not authenticated).
 
 ![filepickerlogin](pix/filepickerlogin.png)

 When the button is clicked a pop-up window or a new tab
 is opened and the user will be requested to autorize the App.
When the user is successfully authenticated he sees a schedular listing of his available files.

![Plugin-Struktur](pix/FilePickerredblock.png)

The first icon in the red block is used to dynamically load the content. The second button can be used to logout. The last button is only available 
for admins and redirects to the `oauth2owncloud` settings.

For additional information *(only available in german)* please visit our [documentation page](https://pssl16.github.io).
# German

Dieses Repository Plugin bietet eine Schnittstelle zu einer ownCloud Instanz. Zur Nutzung dieses Plugins wird zuerst das
[tool_oauth2owncloud Plugin](https://github.com/pssl16/moodle-tool_oauth2sciebo) benötigt. Die Installation ist anders nicht möglich, bevor das admin_tool installiert wurde.
## Installation

Das Plugin muss in `repository/owncloud` platziert werden.

### Admin Einstellungen
Bitte beachten Sie, dass in den Settings des admin_tool alle notwendigen Einträge getätigt wurden, ansonsten funktioniert die Authentifizierung des Repositorys nicht. 
Repositorys Plugins müssen in Moodle von einem Administrator unter dem Menüpunkt `Website-Administration>PluginsRepositories>Übersicht` aktiviert werden. 
Der Administrator kann dem Repository zusätzlich unter `Einstellungen` einen globalen Namen geben.


### Nutzer Sicht
Das Repository ist sowohl in den Kursen als auch für private Instanzen verfügbar und muss nicht mehr hinzugefügt werden. 
Kurs Administratoren können das Repository jedoch unter `Speicherorte` löschen. 
Die Nutzung lässt sich von Personen außer dem Admin nicht auf bestimmte Nutzer oder Aktivitäten im Kurs einschränken. 
Im File Picker sieht der Nutzer (wenn er nicht angemeldet ist) zunächst einen Login Button. 

 ![filepickerlogin](pix/filepickerlogin.png)
 
Drückt er diesen wird er in einem Popup-Window oder in einem neuen Tab aufgefordert sich in ownCloud anzumelden und die App zu autorisieren. 
Nach erfolgreicher Autorisierung sieht der Nutzer eine tabellarische Auflistung der vorhandenen Dateien:

![Plugin-Struktur](pix/FilePickerredblock.png)

Im roten Kasten sehen sie Buttons um den Inhalt neu zu laden, sich auszuloggen und nur als Admin sieht man den letzten Button in dem man die Einstellungen des OAuth2 admin_tool bearbeiten kann.

Für genauere Informationen besuchen sie unsere [Website Dokumentation](https://pssl16.github.io).