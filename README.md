# owncloud Repository Plugin for Moodle

[![Build Status](https://travis-ci.org/learnweb/moodle-repository_owncloud.svg?branch=master)](https://travis-ci.org/learnweb/moodle-repository_owncloud)
[![Coverage Status](https://coveralls.io/repos/github/learnweb/moodle-repository_owncloud/badge.svg)](https://coveralls.io/github/learnweb/moodle-repository_owncloud)

This plugin enables Moodle users to have direct access to their private files from ownCloud in the *Moodle file picker* and the *URL resource module*,
enabling to upload files into Moodle directly from their ownCloud,
without having to download it to their local machine first.  

Is your institution using multiple ownCloud servers? Don't worry, 
  a Moodle administrator can connect multiple ownCloud servers that are
    then presented separately to the users. They can't add their own ownCloud servers, though.


This plugin was originally created by Information Systems students of the project seminar sciebo@Learnweb 
at the University of Münster in 2016-17; see https://github.com/pssl16 for an archive(!) of their great work.
Learnweb (University of Münster) took over maintenance in 2017.

## Installation

This plugin requires configuration in ownCloud (add Moodle as an allowed client)
  as well as in Moodle (add ownCloud servers to which users will be able to connect).
   
**1. Add Moodle as a client to ownCloud**

*Prerequisites: Current ownCloud installation (Recommended: Version 10+) with enabled HTTPS and the [oauth2 ownCloud app](https://github.com/owncloud/oauth2).*

Log in as an administrator. Go to `Settings ► User authentication` and add your Moodle installation as a client:

| Name             | Redirection URI                               |
| ---------------- | --------------------------------------------- |
| Your Moodle name | Your Moodle URL + `/admin/oauth2callback.php` | 

For example, if your users reach Moodle at `https://moodle.example.com`,
your redirection URI would be `https://moodle.example.com/admin/oauth2callback.php`. 
The name can be chosen freely, but note that it will presented to ownCloud users,
so the name should be self-explanatory to them.

After adding the client, the table displays a corresponding Client Identifier and a secret.
Those will be required for the configuration in Moodle, so keep them at hand.

**2. Install this plugin to Moodle**

Copy the content of this repository to `repository/owncloud`.
No additional settings are displayed to the admin when installing the plugin. 
However, when the repository is enabled, the admin has to select an issuer which defines the ownCloud server.

The next steps describe how the necessary issuer is created in Moodle's central OAuth 2 services settings.
Afterwards, an ownCloud repository instance is created using that issuer.

**3. Create OAuth 2 Issuer**

You need to configure Moodle so that it knows how to talk to your ownCloud server.
For this, a so-called OAuth 2 issuer has to be registered in the admin menu `Site administration ► Server ► OAuth 2 services`.
There, select `Create custom service`.

Choose the name freely; it will only be shown to you.
Enter ClientID and Secret from the ownCloud settings of step 1.
As Service base URL, enter the full URL to your ownCloud installation, including a custom port (if any).
For example, if the ownCloud installation is at `https://owncloud.example.com:8000/oc/`, then this is the base URL.
Ignore the other settings and click `Save changes`.

Afterwards, your issuer is listed in a table.
There, click `Configure endpoints` to configure the services that we want to use, as ownCloud does not support auto discovery.
For the ownCloud Repository plugin four endpoints have to be registered that are ownCloud-specific: 
   
| Endpoint name             | Endpoint URL                                              |
| ------------------------- | --------------------------------------------------------- |
| `token_endpoint`          | Base URL + `/index.php/apps/oauth2/api/v1/token`          |
| `authorization_endpoint`  | Base URL + `/index.php/apps/oauth2/authorize`             |
| `webdav_endpoint`         | Base URL + `/remote.php/webdav/`                          |
| `ocs_endpoint`            | Base URL + `/ocs/v1.php/apps/files_sharing/api/v1/shares` |

Given the Base URL example above, an exemplary `token_endpoint` URL is `https://owncloud.example.com:8000/oc/index.php/apps/oauth2/api/v1/token`.

Return to the issuer overview and click on `Configure user field mappings`. Enter the following mappings:

| External field name | Internal field name |
| ------------------- | ------------------- |
| `ocs-data-email`    | `email`             | 
| `ocs-data-id`       | `username`          |

This is sufficient to use the ownCloud repository!
For further information on configuring OAuth 2 clients visit the [Moodle documentation on OAuth 2](https://docs.moodle.org/dev/OAuth_2_API).

**4. Create a repository instance**

Now that the ownCloud issuer is configured, it can be associated with an instance of the repository. 
Go to the repository settings ```Site administration ► Plugins ► Repositories ► Manage repositories``` 
and enable the ownCloud respository (`Enabled and visible`). 
When asked for special user permissions, do not check any boxes. As they may not configure OAuth 2 issuers, these permissions are not that useful.
Then, open the `Settings` of the ownCloud repository and click `Create a repository instance`.
Enter a name that will be displayed to Moodle users and select the configured issuer.
A text underneath the select box tells you which issuers are suited for use with this repository.
If your issuer does not show up, double-check the issuer settings; particularly all URLs (base URL and endpoints) and the names of the endpoints.

![Instance configuration form](https://user-images.githubusercontent.com/432117/28979878-de28da98-794b-11e7-9ce8-b48e73daed4e.png)

Afterwards, everything is configured and ready to go! Let's see what this looks like for your users:

## User View

The repository is available in all activities where the file picker is used.
However, course admins can disable it in the `Course Administration ► Repositories` menu.

In the file picker a login button is displayed (assuming that the user is not authenticated yet):

![File picker login](https://user-images.githubusercontent.com/432117/27905348-f4305ca8-623f-11e7-91c6-5bef1340bcd9.png)

When the button is clicked a pop-up window or a new tab is opened and the user will be requested to login at the ownCloud instance and authorise access from Moodle.
If authorisation is granted, the user sees a tabular listing of the files available:

![File picker](https://user-images.githubusercontent.com/432117/27905344-f40e4a78-623f-11e7-9332-4859f8666eff.png)

Here the user can select files, reload the content and logout. The settings button is only displayed to admins, who will be redirected to the repository settings.

## Hints for Developers and Contributors
 
The plugin uses Moodle's OAuth 2 API that was added in Moodle 3.3.


It makes use of a slightly modified version of Moodle's `webdav_client` (`lib/webdavlib.php`)
  that was extended to incorporate OAuth-2-authenticated requests. 
The modified version is the class `repository_owncloud\owncloud_client`; the effective differences to
the original `webdavlib.php` are described by the patch in `classes/webdavlib.php.diff`.

Additional information can be found in our [original documentation (in German)](https://pssl16.github.io).

