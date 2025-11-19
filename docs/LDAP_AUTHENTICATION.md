# Local Active Directory (LDAP) Authentication Guide

This guide explains how to configure Token Inventory to use **on-premises Active Directory** authentication instead of Azure AD/Entra ID.

## Overview

The application now supports **three authentication methods**:
1. **Azure AD / Entra ID** - Cloud-based authentication
2. **Local Active Directory (LDAP)** - On-premises AD authentication
3. **None** - No authentication (development only)

## When to Use LDAP Authentication

Use LDAP authentication when:
- ✅ You have an on-premises Active Directory environment
- ✅ You don't want to use Azure AD/Entra ID
- ✅ You want users to authenticate with their domain credentials
- ✅ Your Kubernetes cluster can reach your domain controllers

## Prerequisites

### Required
- **Active Directory Domain Services** running and accessible
- **Network connectivity** from Kubernetes pods to domain controllers
- **Service account** (optional but recommended) for LDAP searches
- **PHP LDAP extension** (included in the Docker image)

### Active Directory Requirements
- Domain controllers accessible on port 389 (LDAP) or 636 (LDAPS)
- Service account with read access to user objects
- (Optional) Security groups for access control

## Configuration

### Method 1: Kubernetes Deployment

Edit `k8s/secret.yaml`:

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: token-inventory-secrets
  namespace: token-inventory
type: Opaque
stringData:
  # Set authentication method to LDAP
  AUTH_METHOD: "ldap"

  # LDAP Configuration (multiple hosts supported, comma-separated)
  LDAP_HOST: "dc1.company.com,dc2.company.com,dc3.company.com"
  LDAP_PORT: "389"                # 389 for LDAP, 636 for LDAPS
  LDAP_USE_TLS: "false"           # Set to "true" for LDAPS or StartTLS
  LDAP_BASE_DN: "DC=company,DC=com"  # Your domain base DN

  # Service account for searches (recommended)
  LDAP_BIND_DN: "CN=TokenService,OU=Service Accounts,DC=company,DC=com"
  LDAP_BIND_PASSWORD: "YourServiceAccountPassword"

  # User search filter
  LDAP_USER_FILTER: "(sAMAccountName={username})"

  # Optional: Restrict to specific groups
  # Use group CNs, comma-separated: "Domain Admins,IT Staff,Token Managers"
  LDAP_ALLOWED_GROUPS: ""

  LDAP_TIMEOUT: "10"
```

### Method 2: Local Development (.env)

Copy `.env.example` to `.env` and configure:

```bash
# Authentication method
AUTH_METHOD=ldap

# LDAP Configuration (multiple hosts supported for failover)
LDAP_HOST=dc1.company.local,dc2.company.local
LDAP_PORT=389
LDAP_USE_TLS=false
LDAP_BASE_DN=DC=company,DC=local

# Service account
LDAP_BIND_DN=CN=TokenService,OU=Service Accounts,DC=company,DC=local
LDAP_BIND_PASSWORD=YourPassword

# User search
LDAP_USER_FILTER=(sAMAccountName={username})

# Optional group restriction
LDAP_ALLOWED_GROUPS=Domain Admins,IT Staff

LDAP_TIMEOUT=10
```

## Configuration Parameters

| Parameter | Description | Example | Required |
|-----------|-------------|---------|----------|
| `AUTH_METHOD` | Set to `ldap` or `ad` | `ldap` | Yes |
| `LDAP_HOST` | Domain controller hostname(s) or IP(s). Multiple hosts supported (comma-separated) for automatic failover. | `dc1.company.com,dc2.company.com` | Yes |
| `LDAP_PORT` | LDAP port (389 or 636) | `389` | No (default: 389) |
| `LDAP_USE_TLS` | Use LDAPS or StartTLS | `true` or `false` | No (default: false) |
| `LDAP_BASE_DN` | Base DN for searches | `DC=company,DC=com` | Yes |
| `LDAP_BIND_DN` | Service account DN | `CN=Service,OU=Accounts,DC=company,DC=com` | No* |
| `LDAP_BIND_PASSWORD` | Service account password | `password123` | No* |
| `LDAP_USER_FILTER` | User search filter | `(sAMAccountName={username})` | No (default provided) |
| `LDAP_ALLOWED_GROUPS` | Allowed group CNs | `Domain Admins,IT Staff` | No |
| `LDAP_TIMEOUT` | Connection timeout (seconds) | `10` | No (default: 10) |

**Note:** Service account (BIND_DN/PASSWORD) is optional but **highly recommended**. Without it, anonymous LDAP binds must be enabled (not recommended for security).

## Finding Your LDAP Values

### Get Base DN
```powershell
# PowerShell on domain-joined machine
(Get-ADDomain).DistinguishedName
# Output: DC=company,DC=com
```

### Get User DN Format
```powershell
# Find a user
Get-ADUser jdoe
# Output shows DistinguishedName
# CN=John Doe,OU=Users,DC=company,DC=com
```

### Get Domain Controller
```powershell
# Get domain controllers
Get-ADDomainController -Filter *
# Use the Name or HostName
```

### Get Group Information
```powershell
# Find a group
Get-ADGroup "IT Staff"
# Note the CN (Common Name)
```

## Network Configuration

### Kubernetes Network Access

Your Kubernetes pods need to reach your domain controllers on port 389 (LDAP) or 636 (LDAPS).

#### Option 1: Direct Network Access
If your Kubernetes cluster is on the same network as your domain controllers, it should work automatically.

#### Option 2: Network Policy
Create a network policy to allow egress to domain controllers:

```yaml
apiVersion: networking.k8s.io/v1
kind: NetworkPolicy
metadata:
  name: allow-ldap
  namespace: token-inventory
spec:
  podSelector:
    matchLabels:
      app: token-inventory
  policyTypes:
  - Egress
  egress:
  - to:
    - ipBlock:
        cidr: 10.0.1.10/32  # Your DC IP
    ports:
    - protocol: TCP
      port: 389
    - protocol: TCP
      port: 636
```

#### Option 3: Service for External DC
If your DC is outside the cluster:

```yaml
apiVersion: v1
kind: Service
metadata:
  name: ldap-external
  namespace: token-inventory
spec:
  type: ExternalName
  externalName: dc01.company.com
```

Then use `LDAP_HOST=ldap-external` in your configuration.

### Testing Connectivity

From within a pod:

```bash
# Test LDAP connectivity
kubectl exec -it <pod-name> -n token-inventory -- bash
apt-get update && apt-get install -y ldap-utils
ldapsearch -H ldap://dc01.company.com -x -b "DC=company,DC=com" -LLL "(objectClass=user)" cn
```

## High Availability Setup

### Multiple Domain Controllers
The application supports **automatic failover** across multiple domain controllers:

```yaml
# Comma-separated list of domain controllers
LDAP_HOST: "dc1.company.com,dc2.company.com,dc3.company.com"
```

**How it works:**
1. The application tries to connect to the first host
2. If connection fails, it automatically tries the next host
3. Continues until a successful connection is established
4. Logs which host was successfully connected

**Benefits:**
- ✅ Automatic failover if primary DC is down
- ✅ No manual intervention required
- ✅ Improved reliability for authentication

**Example Configurations:**

```bash
# Multiple DCs by hostname
LDAP_HOST=dc01.company.com,dc02.company.com,dc03.company.com

# Mix hostnames and IPs
LDAP_HOST=dc01.company.com,10.0.1.10,dc03.company.com

# Different sites for disaster recovery
LDAP_HOST=dc-site1.company.com,dc-site2.company.com
```

## Security Considerations

### 1. Use TLS/SSL
**Recommended:** Enable `LDAP_USE_TLS: "true"` for encrypted connections.

```yaml
LDAP_USE_TLS: "true"
LDAP_PORT: "636"  # LDAPS port
```

### 2. Service Account Permissions
The service account should have **minimal permissions**:
- Read access to user objects
- Read access to group memberships
- No write permissions needed

### 3. Group-Based Access Control
Restrict access to specific groups:

```yaml
LDAP_ALLOWED_GROUPS: "Token Admins,IT Department"
```

Users must be members of at least one listed group to access the application.

### 4. Secure Secrets
Never commit `k8s/secret.yaml` with real credentials to Git. Use:
- Azure Key Vault
- HashiCorp Vault
- Kubernetes Sealed Secrets
- External Secrets Operator

## User Login Process

1. User navigates to application URL
2. Application shows LDAP login form
3. User enters domain username (e.g., `jdoe`, not `DOMAIN\jdoe` or `jdoe@company.com`)
4. Application searches for user in AD
5. Application attempts to bind with user's credentials
6. If successful, checks group membership (if configured)
7. User is granted access

### Login Username Formats

The application accepts the **sAMAccountName** (username) by default:
- ✅ `jdoe`
- ✅ `john.doe`
- ❌ `COMPANY\jdoe` (not needed)
- ❌ `jdoe@company.com` (not needed)

## Troubleshooting

### Cannot Connect to Domain Controller

**Check network connectivity:**
```bash
kubectl exec -it <pod-name> -n token-inventory -- ping dc01.company.com
kubectl exec -it <pod-name> -n token-inventory -- telnet dc01.company.com 389
```

**Check DNS resolution:**
```bash
kubectl exec -it <pod-name> -n token-inventory -- nslookup dc01.company.com
```

### Authentication Fails

**Check service account:**
```bash
# Test with ldapsearch
ldapsearch -H ldap://dc01.company.com \
  -D "CN=Service,OU=Accounts,DC=company,DC=com" \
  -w "password" \
  -b "DC=company,DC=com" \
  "(sAMAccountName=jdoe)"
```

**Check application logs:**
```bash
kubectl logs -n token-inventory -l app=token-inventory | grep LDAP
```

### User Not Found

**Verify user search filter:**
- Default: `(sAMAccountName={username})`
- For UPN: `(userPrincipalName={username}@company.com)`
- For email: `(mail={username}@company.com)`

**Verify base DN covers user location:**
```powershell
Get-ADUser jdoe | Select DistinguishedName
```

### Group Access Denied

**Check group membership:**
```powershell
Get-ADUser jdoe -Properties MemberOf | Select -ExpandProperty MemberOf
```

**Check group names:**
Use the CN (Common Name), not the full DN:
- ✅ `IT Staff`
- ❌ `CN=IT Staff,OU=Groups,DC=company,DC=com`

### TLS/SSL Issues

**For self-signed certificates:**
```yaml
# In Deployment, add:
env:
- name: LDAPTLS_REQCERT
  value: "never"
```

**Production:** Use valid certificates and don't skip verification.

## Advanced Configuration

### Custom User Filter

For complex AD structures:

```yaml
# Search by UPN
LDAP_USER_FILTER: "(userPrincipalName={username}@company.com)"

# Search by email
LDAP_USER_FILTER: "(mail={username}@company.com)"

# Search in specific OU
LDAP_BASE_DN: "OU=Employees,DC=company,DC=com"

# Multiple criteria
LDAP_USER_FILTER: "(|
  (sAMAccountName={username})
  (userPrincipalName={username}@company.com)
)"
```

### Multiple Domain Controllers

Use a load balancer or DNS round-robin:

```yaml
LDAP_HOST: "ldap.company.com"  # DNS resolves to multiple DCs
```

Or use the domain name:

```yaml
LDAP_HOST: "company.com"  # Will use SRV records
```

### Read-Only Domain Controllers (RODC)

Works with RODCs for authentication. The service account must have read access on the RODC.

## Switching from Azure AD to LDAP

1. **Update AUTH_METHOD:**
   ```yaml
   AUTH_METHOD: "ldap"
   ```

2. **Configure LDAP settings** in `k8s/secret.yaml`

3. **Redeploy:**
   ```bash
   kubectl apply -f k8s/secret.yaml
   kubectl rollout restart deployment/token-inventory -n token-inventory
   ```

4. **Test login** with domain credentials

## Using Both Azure AD and LDAP

You can only use **one authentication method at a time**. To switch:
1. Update `AUTH_METHOD` in secrets
2. Restart deployment
3. Users will see the appropriate login screen

## Performance Considerations

- **Connection pooling:** Not implemented; each request creates new LDAP connection
- **Caching:** User sessions cached for 8 hours (configurable)
- **Timeout:** Default 10 seconds for LDAP operations

## Example Configurations

### Small Office
```yaml
AUTH_METHOD: "ldap"
LDAP_HOST: "192.168.1.10"  # DC IP
LDAP_PORT: "389"
LDAP_BASE_DN: "DC=company,DC=local"
LDAP_USER_FILTER: "(sAMAccountName={username})"
# No service account, anonymous bind
```

### Enterprise with Security
```yaml
AUTH_METHOD: "ldap"
LDAP_HOST: "ldap.company.com"
LDAP_PORT: "636"
LDAP_USE_TLS: "true"
LDAP_BASE_DN: "DC=company,DC=com"
LDAP_BIND_DN: "CN=TokenService,OU=Service Accounts,DC=company,DC=com"
LDAP_BIND_PASSWORD: "SecurePassword123!"
LDAP_USER_FILTER: "(sAMAccountName={username})"
LDAP_ALLOWED_GROUPS: "Token Admins,Security Team"
LDAP_TIMEOUT: "15"
```

### Multi-Domain Forest
```yaml
# Use Global Catalog
LDAP_HOST: "gc.company.com"
LDAP_PORT: "3268"  # GC port
LDAP_BASE_DN: "DC=company,DC=com"  # Forest root
```

## Support

For LDAP authentication issues:
1. Check application logs for LDAP error messages
2. Test connectivity to domain controller
3. Verify service account permissions
4. Test with ldapsearch command-line tool
5. Check firewall rules between Kubernetes and DC

## Additional Resources

- [Active Directory LDAP Documentation](https://docs.microsoft.com/en-us/windows/win32/ad/active-directory-ldap)
- [PHP LDAP Functions](https://www.php.net/manual/en/book.ldap.php)
- [Kubernetes Network Policies](https://kubernetes.io/docs/concepts/services-networking/network-policies/)
