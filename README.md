# 2FA plugin from roundcube

Used for 2 - step auth for our webmail-clients, it's not in productuction use.
NOT compatible with roundcube 1.4 yet, however it should be an simple porting to 1.4

## How it works 
Plugin is located at settings -> 2FA auth

Users render an key or types in their own(not secure), then displays the QR code to add 2 FA to the email account.
The user is however allowed to create up to 4 rescue codes. Theese in located in the roundcube database.

The only way to unlock an email-account is to disable the setting twofactor_gauthenticator in the settings-column at the users-table in the database.
