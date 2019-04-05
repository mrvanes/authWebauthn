# SimpleSAMLphp authWebauthn authproc module

This module implements a simpleSAMLphp authproc that can be used to enforce second factor authentication using Webauthn compatible security devices.

## Usage
The authproc can be configured as registration or validation proc. Enable the proc on any authsource. One for registration purposes and one for validation purposes:

```
    'sp-register' => [
        'saml:SP',
        'entityID' => null,
        'idp' => 'idp-register',
        'discoURL' => null,
        'authproc' => [
            // Add Webauthn second factor registration
            100 => ['class' => 'authWebauthn:WebAuthn',
                    'id' => 'uid',
                    'database' => '/var/www/webauthn/users/keys.sq3',
                    'purpose' => 'register'
                    ],
        ],
    ],
    'sp-login' => [
        'saml:SP',
        'entityID' => null,
        'idp' => 'idp-login',
        'discoURL' => null,
        'authproc' => [
            // Add Webauthn second factor validation
            100 => ['class' => 'authWebauthn:WebAuthn',
                    'id' => 'uid',
                    'database' => '/var/www/webauthn/users/keys.sq3',
                    'purpose' => 'validate'
                    ],
        ],
    ],
```
