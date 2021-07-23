# authX509toSAML

The authX509toSAML module provides X509 authentication and attribute translation from certificate
attributes to SAML attributes.

## Using the authX509toSAML authentication source with SimpleSAMLphp

### Configuring Apache

This module assumes that the server requests a client certificate, and
stores it in the environment variable SSL_CLIENT_CERT. This can be achieved
with such a configuration:

```config
    SSLEngine on
    # Configure the Server part of TLS
    SSLCertificateFile /etc/openssl/certs/server.crt
    SSLCertificateKeyFile /etc/openssl/private/server.key
    SSLCACertificateFile /etc/openssl/certs/ca.crt
    # Configure Client certificate authentication
    SSLVerifyClient require
    SSLVerifyDepth 1
    # Configure which CAs the server will trust for signing client certificates
    SSLCACertificatePath "/usr/share/igtf-policy/classic"
    SSLOptions +ExportCertData
```

Note that SSLVerifyClient can be set to optional if you want to support
both certificate and plain login authentication. Alternatively SSLVerifyClient can be
set explicitly on

```config
    <Location "/simplesaml/saml2/idp/SSOService.php">
```

### Install authX509toSAML module

```sh
   git clone https://github.com/jkakavas/authX509toSAML.git
   cp -r authX509toSAML /var/simplesamlphp/modules/
```

### Setting up the authX509toSAML module

The first thing you need to do is to enable the module:

```sh
    touch /var/simplesamlphp/modules/authX509toSAML/enable
```

Then you must add it as an authentication source in /var/simplesamlphp/config/authsources.php . Here is an
example authsources.php entry:

```php
    'x509' => [
        'authX509toSAML:X509userCert',
        'authX509toSAML:cert_name_attribute': 'CN',
        'authX509toSAML:assertion_name_attribute': 'displayName',
        'authX509toSAML:assertion_dn_attribute': 'distinguishedName',
        'authX509toSAML:assertion_o_attribute': 'o',
        'authX509toSAML:assertion_assurance_attribute': 'eduPersonAssurance',
        'authX509toSAML:parse_san_emails': TRUE
        'authX509toSAML:parse_policy': TRUE,
        'authX509toSAML:export_eppn': FALSE,
    ],
```

The configuration options are as following:

- **cert_name_attribute** is the name of the attribute in the certificate
  where the name of the certificate subject is to be found
- **assertion_name_attribute** is the attribute in the SAML assertion that the name
  of the certificate subject will be mapped to
- **assertion_dn_attribute** is the attribute in the SAML assertion where the DN of the
  certificate subject will be mapped to

- **assertion_o_attribute** is the attribute in the SAML assertion where the organisation name (if present)
  in the certificate subject will be mapped to. Defaults to `'o'`. If set to `null`, the module will not attempt to extract the value from the certificate subject.

- **assertion_dn_attribute** is the attribute in the SAML assertion where the certificatePolicy
  attribute of the certificate will be mapped to

- **parse_san_emails** controls whether the module will attempt to parse Subject Alternate
  Names to find possible email addresses for the certificate subject
- **parse_policy** controls whether the module will attempt to parse Certificate Policy
- **export_eppn** controls whether the module will attempt to parse an eduPersonPrincipalName
  value from the certificate subject value (i.e. for Grid Robot certificates)

All the above parameters are optional, since the code contains sane defaults for all of them (the values shown in the example above)

### Setting up attribute-based authorisation

You can use the [authorize](https://simplesamlphp.org/docs/stable/authorize:authorize)
module in order to control access based on the attributes returned by the
authX509toSAML module after certificate authentication.
First, you need to make sure that the authorize module is enabled.
The presence of the `default-enable` file indicates that the module is
enabled by default, so you should remove `disable` if present:

```sh
    rm /var/simplesamlphp/modules/authorize/disable
```

Then you must add the module as an authentication processing (authproc) filter,
either in `config.php` or in `saml20-idp-hosted.php`. Here is an
example authproc configuration in `saml20-idp-hosted.php`:

```php
    'authproc' => [

        92 => [
            'class' => 'authorize:Authorize',
            'regex' => false,
            'eduPersonAssurance' => [
                '1.2.840.113612.5.2.2.1',
                '1.2.840.113612.5.2.2.5',
            ],
        ],

    ],
```

The configuration above only allows access to certificates including either
IGTF Classic (`1.2.840.113612.5.2.2.1`) or MICS (`1.2.840.113612.5.2.2.5`) in
their policies (assuming the authX509toSAML module has been configured to map
certificate policies to the eduPersonAssurance attribute).

## Compatibility matrix

This table matches the module version with the supported SimpleSAMLphp version.

| Module | SimpleSAMLphp |
| :----: | :-----------: |
|  v1.x  |     v1.14     |

## License

Licensed under the Apache 2.0 license, for details see `LICENSE`.
