# Do not use in production
Please be aware that this Plugin is WIP. 
It is a conduction of the result of the project seminar sciebo@Learnweb of the University of Münster. 
** Do not use in production! ** Plugins and their structure **will** be subject to change. 
We will **NOT** support any upgrade paths from this release.

Nevertheless, we are actively working on a release. We would be extremely happy for (non-production) test users and developer contributions!

# Moodle Repository Plugin `owncloud`

[![Build Status](https://travis-ci.org/learnweb/moodle-repository_owncloud.svg?branch=master)](https://travis-ci.org/learnweb/moodle-repository_owncloud)
[![codecov](https://codecov.io/gh/learnweb/moodle-repository_owncloud/branch/master/graph/badge.svg)](https://codecov.io/gh/learnweb/moodle-repository_owncloud)

# English

This plugin enables Moodle users to have direct access to their private files from ownCloud in the *Moodle file picker* and the *URL resource module*.
The plugin uses Moodle's OAuth 2 API that was added in Moodle 3.3.  


Originally created by the project seminar sciebo@Learnweb of the University of Münster; see https://github.com/pssl16 for an archive of their great work.

## Installation

Copy the content of this repository to `repository/owncloud`. No additional settings are displayed to the admin when installing the plugin. 
However, when enabling the plugin the admin has to choose an issuer for authentication.

## Admin Settings

Repository plugins are activated under `Site Administration ► Plugins ► Repositories`.
The following text describes how the necessary issuer is created with the Moodle API and secondly 
how the issuer can be chosen.

### Create OAuth 2 Issuer
You need to configure Moodle so that it knows how to talk to your ownCloud server.
For this, a so-called OAuth 2 issuer has to be registered in the admin menu `Dashboard ► Site administration ► Server ► OAuth 2 services`.
When adding the issuer the ClientID, Secret and baseurl are necessary.
ClientID and secret are generated in the ownCloud instance by an ownCloud administrator.
The base URL is the full URL to your ownCloud installation, including a custom port (if any).
Additionally, Moodle has a second interface for adding endpoints. 

For the ownCloud Repository plugin four endpoints have to be registered that are ownCloud-specific (for an example, see below): 
   
| Endpoint name           | Endpoint URL                                              |
| ----------------------- | --------------------------------------------------------- |
| token_endpoint          | Base URL + `/index.php/apps/oauth2/api/v1/token`          |
| authorization_endpoint  | Base URL + `/index.php/apps/oauth2/authorize`             |
| webdav_endpoint         | Base URL + `/remote.php/webdav/`                          |
| ocs_endpoint            | Base URL + `/ocs/v1.php/apps/files_sharing/api/v1/shares` |

   
For example, if your installation is at `https://someinstance.owncloud.de:8000/oc/`, then this is the base URL.
Therefore, the `token_endpoint` URL is `https://someinstance.owncloud.de:8000/oc/index.php/apps/oauth2/api/v1/token`

For further information on OAuth 2 clients visit the [Moodle documentation on OAuth 2](https://docs.moodle.org/dev/OAuth_2_API).

### Choose OAuth 2 Issuer
After the ownCloud issuer was created, it has to be associated with the repository, 
Your newly created issuer can be chosen in the repository settings ```Site administration ► Plugins ► Repositories ► Manage repositories```.

![Select Form](https://user-images.githubusercontent.com/432117/27905346-f42d55d0-623f-11e7-9e1b-ad4782e989d7.png)

The choice of issuer can trigger three different kinds of notifications:
1. Information (Blue Box) which states which issuer is currently chosen
2. Warning (Yellow Box) in case no issuer is chosen
3. Error (Red Box) in case the issuer is not valid for the plugin

## User View

This plugin is available in all activities where the file picker is used.
However, course admins can disable it in the `Course Administration ► Repositories` menu.

In the file picker a login button is displayed (assuming that the user is not authenticated yet):

![File picker login](https://user-images.githubusercontent.com/432117/27905348-f4305ca8-623f-11e7-91c6-5bef1340bcd9.png)

When the button is clicked a pop-up window or a new tab is opened and the user will be requested to login at the ownCloud instance and authorize access from Moodle.
If authorization is granted, the user sees a tabular listing of the files available:

![File picker](https://user-images.githubusercontent.com/432117/27905344-f40e4a78-623f-11e7-9332-4859f8666eff.png)

Here the user can select files, reload the content and logout. The settings button is only displayed to admins, who will be eedirected to the repository settings.

Additional information can be found in our [original documentation (in German)](https://pssl16.github.io).

