# Authentication Options Summary

## Overview

The Token Inventory application now supports **multiple authentication methods**, giving you flexibility to choose the best option for your environment.

## Supported Authentication Methods

| Method | Use Case | Configuration |
|--------|----------|---------------|
| **Azure AD / Entra ID** | Cloud-first organizations, Microsoft 365 | [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md) |
| **Local Active Directory (LDAP)** | On-premises AD environments | [LDAP_AUTHENTICATION.md](LDAP_AUTHENTICATION.md) |
| **None** | Development/testing only | Set `AUTH_METHOD=none` |

## Quick Comparison

### Azure AD / Entra ID
✅ **Pros:**
- Cloud-based, no on-premises infrastructure needed
- Modern OAuth2 flow
- Integrated with Microsoft 365
- Conditional access policies
- Multi-factor authentication built-in

❌ **Cons:**
- Requires Azure AD subscription
- Internet connectivity required
- Two app registrations needed
- More complex initial setup

**Best for:** Organizations using Microsoft 365, cloud-first environments

### Local Active Directory (LDAP)
✅ **Pros:**
- Works with existing on-premises AD
- No cloud dependency
- Simple username/password login
- Direct integration with domain
- No additional licenses required

❌ **Cons:**
- Requires network connectivity to domain controllers
- Manual group management
- Basic authentication (recommend TLS)
- Domain infrastructure required

**Best for:** On-premises organizations, air-gapped environments

### No Authentication
✅ **Pros:**
- No configuration required
- Fastest to set up
- Good for testing

❌ **Cons:**
- **NOT SECURE** - anyone can access
- Only for development/testing
- Not recommended for production

**Best for:** Local development and testing only

## Configuration Examples

### Azure AD Configuration
```yaml
# k8s/secret.yaml or .env
AUTH_METHOD: "azure"
AZURE_AD_TENANT_ID: "your-tenant-id"
AZURE_AD_CLIENT_ID: "your-client-id"
AZURE_AD_CLIENT_SECRET: "your-secret"
AZURE_AD_REDIRECT_URI: "https://your-domain.com/index.php"
AZURE_AD_ALLOWED_GROUPS: "group-id-1,group-id-2"  # Optional
```

### LDAP Configuration
```yaml
# k8s/secret.yaml or .env
AUTH_METHOD: "ldap"
LDAP_HOST: "dc01.company.com"
LDAP_PORT: "389"
LDAP_BASE_DN: "DC=company,DC=com"
LDAP_BIND_DN: "CN=Service,OU=Accounts,DC=company,DC=com"
LDAP_BIND_PASSWORD: "password"
LDAP_ALLOWED_GROUPS: "Domain Admins,IT Staff"  # Optional
```

### No Authentication
```yaml
# k8s/secret.yaml or .env
AUTH_METHOD: "none"
```

## Switching Between Methods

To switch authentication methods:

1. **Update configuration:**
   ```bash
   # Edit k8s/secret.yaml or .env
   nano k8s/secret.yaml
   ```

2. **Change AUTH_METHOD:**
   ```yaml
   AUTH_METHOD: "ldap"  # or "azure" or "none"
   ```

3. **Configure the selected method's parameters**

4. **Restart the application:**
   ```bash
   # Kubernetes
   kubectl apply -f k8s/secret.yaml
   kubectl rollout restart deployment/token-inventory -n token-inventory

   # Docker Compose
   docker-compose down
   docker-compose up
   ```

## User Experience

### Azure AD Login Flow
1. User visits application URL
2. Redirected to Azure AD login page
3. User logs in with Microsoft account
4. Redirected back to application
5. Application checks group membership (if configured)
6. Access granted

### LDAP Login Flow
1. User visits application URL
2. Shown login form
3. User enters domain username and password (e.g., `jdoe`)
4. Application authenticates against AD
5. Application checks group membership (if configured)
6. Access granted

### No Authentication
1. User visits application URL
2. Immediate access (no login required)

## Security Recommendations

### Production Environments
- ✅ **Use Azure AD or LDAP** - Never use "none" in production
- ✅ **Enable group-based access** - Restrict to specific groups
- ✅ **Use HTTPS/TLS** - Encrypt all connections
- ✅ **Regular audits** - Review user access periodically
- ✅ **Strong passwords** - Enforce password policies

### LDAP-Specific
- ✅ **Use service account** - Don't rely on anonymous bind
- ✅ **Enable TLS** - Set `LDAP_USE_TLS: "true"`
- ✅ **Restrict permissions** - Service account should have minimal read-only access
- ✅ **Use secure passwords** - For service account

### Azure AD-Specific
- ✅ **Conditional access** - Enable MFA, device compliance
- ✅ **Rotate secrets** - Regular client secret rotation
- ✅ **Minimal permissions** - Only grant required permissions
- ✅ **Monitor sign-ins** - Review sign-in logs

## Troubleshooting

### Azure AD Issues
See [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md) troubleshooting section

Common issues:
- Redirect URI mismatch
- Expired client secret
- Missing admin consent
- Group ID vs. group name

### LDAP Issues
See [LDAP_AUTHENTICATION.md](LDAP_AUTHENTICATION.md) troubleshooting section

Common issues:
- Cannot reach domain controller
- Invalid service account credentials
- Wrong base DN
- Group name vs. CN mismatch

### Check Current Auth Method
```bash
# Kubernetes
kubectl get secret token-inventory-secrets -n token-inventory -o jsonpath='{.data.AUTH_METHOD}' | base64 -d

# Docker Compose logs
docker-compose logs | grep "AUTH_METHOD"
```

## Migration Scenarios

### From Desktop App to Azure AD
1. Deploy application with `AUTH_METHOD: "azure"`
2. Create Azure AD app registrations
3. Configure Azure AD settings
4. Users log in with Microsoft accounts

### From Desktop App to LDAP
1. Deploy application with `AUTH_METHOD: "ldap"`
2. Configure LDAP connection to domain controller
3. Set up service account
4. Users log in with domain credentials

### From Azure AD to LDAP
1. Update `AUTH_METHOD` from `"azure"` to `"ldap"`
2. Configure LDAP settings
3. Restart deployment
4. Users now log in with domain credentials

### From LDAP to Azure AD
1. Update `AUTH_METHOD` from `"ldap"` to `"azure"`
2. Configure Azure AD settings
3. Restart deployment
4. Users now log in with Microsoft accounts

## Feature Comparison

| Feature | Azure AD | LDAP | None |
|---------|----------|------|------|
| User Authentication | ✅ | ✅ | ❌ |
| Group-Based Access | ✅ | ✅ | ❌ |
| MFA Support | ✅ | ⚠️ (depends on AD) | ❌ |
| Cloud-Based | ✅ | ❌ | N/A |
| On-Premises | ❌ | ✅ | N/A |
| Session Management | ✅ | ✅ | ❌ |
| Production Ready | ✅ | ✅ | ❌ |

## Documentation Links

- **[START_HERE.md](START_HERE.md)** - Complete setup guide
- **[KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md)** - Azure AD deployment guide
- **[LDAP_AUTHENTICATION.md](LDAP_AUTHENTICATION.md)** - LDAP setup guide
- **[QUICK_REFERENCE.md](QUICK_REFERENCE.md)** - Command reference
- **[PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md)** - File organization

## Support

For authentication issues:
1. Check application logs
2. Verify configuration values
3. Test connectivity (for LDAP)
4. Review appropriate documentation (Azure AD or LDAP)
5. Check firewall rules

## Summary

Choose the authentication method that best fits your organization:
- **Azure AD**: Modern, cloud-based, integrated with Microsoft 365
- **LDAP**: Traditional, on-premises, works with existing AD
- **None**: Development/testing only

Both Azure AD and LDAP are production-ready and secure when properly configured.
