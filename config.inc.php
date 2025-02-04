<?php
// if true ALL users must have 2-steps active
$rcmail_config['force_enrollment_users'] = false;

// whitelist, CIDR format available
// NOTE: we need to use .0 IP to define LAN because the class CIDR have a issue about that (we can't use 129.168.1.2/24, for example)
$rcmail_config['whitelist'] = array();

// Admin can disable saving devices for all users (paranoid mode)
// Default: allow saving devices (true)
$rcmail_config['allow_save_device_30days'] = true;

// Make the 2-step field a masked password input type
// Default: form field will be text (false)
$rcmail_config['twofactor_formfield_as_password'] = false;
