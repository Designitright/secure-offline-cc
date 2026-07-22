# Security Policy — Secure Offline CC for WooCommerce

## Supported Versions

| Version | Supported |
|---------|-----------|
| 2.1.x   | ✅ Active support |
| 2.0.x   | ⚠️ Security fixes only |
| < 2.0   | ❌ End of life |

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

If you discover a security vulnerability in this plugin, please report it responsibly:

**Email:** security@design-it-right.com  
**Subject:** `[SECURITY] secure-offline-cc — [brief description]`

### What to Include

- Plugin version affected
- WordPress and WooCommerce versions
- Description of the vulnerability
- Steps to reproduce
- Potential impact assessment
- Any suggested fix (optional)

### Response Timeline

- **Acknowledgment:** Within 48 hours
- **Initial assessment:** Within 5 business days
- **Fix timeline:** Depends on severity — critical issues within 7 days, others within 30 days
- **Credit:** Reporters will be credited in the changelog unless they prefer to remain anonymous

### Scope

Issues we consider in scope:
- SQL injection
- Cross-site scripting (XSS)
- Authentication bypass
- Privilege escalation
- Encryption weaknesses or key exposure
- AJAX endpoint security issues
- Nonce or CSRF vulnerabilities

Issues out of scope:
- Vulnerabilities requiring admin-level access to exploit
- Issues in WordPress core or WooCommerce core (report to those projects)
- Theoretical attacks without demonstrated impact
- Issues requiring physical access to the server

## Security Architecture

This plugin handles payment card data. Our security design principles:

1. **Minimize server-side card exposure** — card data is encrypted immediately, never logged
2. **AES-256-GCM** — authenticated encryption prevents both decryption and tampering without the key
3. **Key separation** — encryption key lives in `wp-config.php`, never in the database
4. **Capability-gated access** — only `manage_woocommerce` users can decrypt
5. **Audit logging** — all access is logged with user, IP, and timestamp
6. **Auto-purge** — encrypted data is purged after a configurable period
7. **No external dependencies** — no license server, no CDN resources, no external APIs

## PCI DSS Notice

This plugin is designed to minimize PCI DSS scope but does not guarantee compliance. Merchants using this plugin remain responsible for their own PCI compliance obligations. We strongly recommend consulting a Qualified Security Assessor (QSA) if you process significant card volume.
