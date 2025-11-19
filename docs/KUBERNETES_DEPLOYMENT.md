# Kubernetes Deployment Guide for TOTP Token Inventory

This guide explains how to deploy the TOTP Token Inventory application to Kubernetes with Azure AD authentication.

## Overview

The application has been converted from a PHP Desktop application to a containerized web application with:
- **Azure AD OAuth2 authentication** to control access
- **Kubernetes deployment** for scalability and high availability
- **Group-based access control** (optional)

## Architecture

```
Internet → Ingress (HTTPS) → Service → Deployment (2+ replicas) → PHP Application
                                                                    ↓
                                                            Azure AD OAuth
```

## Prerequisites

1. **Kubernetes Cluster** (1.19+)
   - AKS, EKS, GKE, or on-premises cluster
   - kubectl configured and connected

2. **Container Registry**
   - Docker Hub, Azure Container Registry, or other registry
   - Push access for your container image

3. **Azure AD App Registrations** (TWO required)

   **App Registration #1: For OAuth (User Authentication)**
   - Used to protect the web application
   - Required permissions: `User.Read`, `openid`, `profile`, `email`
   - Optional: `Group.Read.All` (if using group-based access)
   - Redirect URI: `https://your-domain.com/index.php`
   - Client secret generated

   **App Registration #2: For Graph API (Token Management)**
   - Used by the application to manage hardware tokens
   - Required permissions (with admin consent):
     - `Policy.ReadWrite.AuthenticationMethod`
     - `UserAuthenticationMethod.ReadWrite.All`
     - `User.Read.All`
     - `Directory.Read.All`
   - Client secret generated

4. **Ingress Controller** (e.g., nginx-ingress)

5. **DNS Configuration**
   - Domain name pointing to your ingress controller

## Step 1: Build and Push Docker Image

### Build the image

```bash
cd /mnt/c/Users/chad.jones/token_inventory

# Build the image
docker build -t your-registry/token-inventory:latest .

# Test locally (optional)
docker run -p 8080:80 your-registry/token-inventory:latest
```

### Push to registry

```bash
# Login to your registry
docker login your-registry.com

# Push the image
docker push your-registry/token-inventory:latest
```

## Step 2: Configure Azure AD App Registrations

### App Registration #1: OAuth (Web App Authentication)

1. Go to Azure Portal → Azure Active Directory → App Registrations
2. Click "New registration"
   - Name: `Token Inventory Web Auth`
   - Supported account types: Choose based on your requirements
   - Redirect URI: `https://your-domain.com/index.php`
3. After creation, note the:
   - **Application (client) ID**
   - **Directory (tenant) ID**
4. Go to "Certificates & secrets" → "New client secret"
   - Note the **secret value** (you won't see it again)
5. Go to "API permissions"
   - Add permissions: `User.Read`, `openid`, `profile`, `email`
   - Optional: Add `Group.Read.All` if using group filtering
   - Grant admin consent

### App Registration #2: Graph API (Token Management)

1. Create another app registration
   - Name: `Token Inventory API Access`
2. Note the client ID and create a client secret
3. Add API permissions:
   - `Policy.ReadWrite.AuthenticationMethod`
   - `UserAuthenticationMethod.ReadWrite.All`
   - `User.Read.All`
   - `Directory.Read.All`
4. **Grant admin consent** for all permissions

### Optional: Create Azure AD Group for Access Control

If you want to restrict access to specific users:

1. Create an Azure AD group (e.g., "Token Inventory Admins")
2. Add users to the group
3. Note the **Group Object ID**

## Step 3: Configure Kubernetes Secrets

Edit `k8s/secret.yaml`:

```yaml
apiVersion: v1
kind: Secret
metadata:
  name: token-inventory-secrets
  namespace: token-inventory
type: Opaque
stringData:
  # OAuth Configuration (App Registration #1)
  AZURE_AD_TENANT_ID: "your-tenant-id"
  AZURE_AD_CLIENT_ID: "your-oauth-client-id"
  AZURE_AD_CLIENT_SECRET: "your-oauth-client-secret"
  AZURE_AD_REDIRECT_URI: "https://your-domain.com/index.php"

  # Optional: Restrict to specific Azure AD groups
  # Comma-separated list of group object IDs
  AZURE_AD_ALLOWED_GROUPS: "group-id-1,group-id-2"
```

**⚠️ IMPORTANT**: Do NOT commit this file to Git with real secrets!

## Step 4: Configure Kubernetes Deployment

Edit `k8s/deployment.yaml`:

Update the image reference:

```yaml
spec:
  containers:
  - name: token-inventory
    image: your-registry/token-inventory:latest
```

## Step 5: Configure Ingress

Edit `k8s/ingress.yaml`:

```yaml
spec:
  tls:
  - hosts:
    - your-domain.com  # Replace with your domain
    secretName: token-inventory-tls
  rules:
  - host: your-domain.com  # Replace with your domain
```

## Step 6: Deploy to Kubernetes

### Using kubectl

```bash
cd k8s

# Apply all manifests
kubectl apply -f namespace.yaml
kubectl apply -f secret.yaml
kubectl apply -f deployment.yaml
kubectl apply -f service.yaml
kubectl apply -f ingress.yaml
```

### Using kustomize

```bash
cd k8s
kubectl apply -k .
```

### Verify deployment

```bash
# Check pods
kubectl get pods -n token-inventory

# Check service
kubectl get svc -n token-inventory

# Check ingress
kubectl get ingress -n token-inventory

# View logs
kubectl logs -n token-inventory -l app=token-inventory
```

## Step 7: Configure TLS/SSL Certificate

### Option A: Using cert-manager (recommended)

If you have cert-manager installed:

```yaml
apiVersion: cert-manager.io/v1
kind: Certificate
metadata:
  name: token-inventory-tls
  namespace: token-inventory
spec:
  secretName: token-inventory-tls
  issuerRef:
    name: letsencrypt-prod
    kind: ClusterIssuer
  dnsNames:
  - your-domain.com
```

### Option B: Manual certificate

```bash
kubectl create secret tls token-inventory-tls \
  --cert=path/to/cert.crt \
  --key=path/to/cert.key \
  -n token-inventory
```

## Step 8: Access the Application

1. Navigate to `https://your-domain.com`
2. You will be redirected to Azure AD login
3. After authentication, you'll be redirected back to the application
4. If group filtering is enabled, only members of allowed groups can access

## Step 9: Configure Token Management Credentials

After accessing the application:

1. The app will prompt for **Graph API credentials** (App Registration #2)
2. Enter:
   - **Tenant ID**
   - **Client ID** (from App Registration #2)
   - **Client Secret** (from App Registration #2)
3. These credentials are stored in the user's session (cookies)

## Authentication Flow

1. User accesses application URL
2. Application checks for Azure AD session
3. If not authenticated → Redirect to Azure AD OAuth login
4. User logs in with their Azure AD account
5. Azure AD redirects back with authorization code
6. Application exchanges code for access token
7. Application verifies user's group membership (if configured)
8. User is granted access to the application
9. User configures Graph API credentials for token management

## Security Considerations

1. **Secrets Management**
   - Never commit `secret.yaml` with real credentials to Git
   - Consider using external secret management (Azure Key Vault, Sealed Secrets, etc.)

2. **Network Policies**
   - Restrict pod-to-pod communication
   - Only allow ingress from ingress controller

3. **RBAC**
   - Use Kubernetes RBAC to control who can deploy/manage the application

4. **TLS**
   - Always use HTTPS in production
   - Use valid certificates (not self-signed)

5. **Group-Based Access**
   - Regularly review group memberships
   - Use dedicated groups for application access

## Scaling

To scale the application:

```bash
kubectl scale deployment token-inventory -n token-inventory --replicas=5
```

Or edit the deployment:

```yaml
spec:
  replicas: 5
```

## Monitoring

Check application health:

```bash
# Pod status
kubectl get pods -n token-inventory

# Logs
kubectl logs -n token-inventory -l app=token-inventory --tail=100 -f

# Events
kubectl get events -n token-inventory --sort-by='.lastTimestamp'
```

## Troubleshooting

### Pods not starting

```bash
kubectl describe pod <pod-name> -n token-inventory
kubectl logs <pod-name> -n token-inventory
```

### Authentication issues

1. Check Azure AD redirect URI matches exactly
2. Verify client secret is correct and not expired
3. Check admin consent was granted for required permissions
4. Review application logs for error messages

### 403 Access Denied

- User may not be in the allowed Azure AD groups
- Check `AZURE_AD_ALLOWED_GROUPS` configuration
- Verify group membership in Azure AD

### Token management not working

- Verify Graph API credentials (App Registration #2)
- Check that admin consent was granted for all required permissions
- Review application logs for API errors

## Updating the Application

To deploy a new version:

```bash
# Build new image with version tag
docker build -t your-registry/token-inventory:v1.1.0 .
docker push your-registry/token-inventory:v1.1.0

# Update deployment
kubectl set image deployment/token-inventory \
  token-inventory=your-registry/token-inventory:v1.1.0 \
  -n token-inventory

# Or update the YAML and apply
kubectl apply -f k8s/deployment.yaml
```

## Backup and Disaster Recovery

The application is stateless, but user session data is stored in cookies. No persistent storage is required.

To backup configuration:

```bash
# Export secrets (be careful with these!)
kubectl get secret token-inventory-secrets -n token-inventory -o yaml > backup-secret.yaml

# Export all resources
kubectl get all -n token-inventory -o yaml > backup-all.yaml
```

## Uninstalling

To remove the application:

```bash
kubectl delete namespace token-inventory
```

Or remove resources individually:

```bash
kubectl delete -f k8s/ingress.yaml
kubectl delete -f k8s/service.yaml
kubectl delete -f k8s/deployment.yaml
kubectl delete -f k8s/secret.yaml
kubectl delete -f k8s/namespace.yaml
```

## Additional Resources

- [Original Token Inventory Documentation](../README.md)
- [Kubernetes Documentation](https://kubernetes.io/docs/)
- [Azure AD OAuth Documentation](https://docs.microsoft.com/en-us/azure/active-directory/develop/)
- [Microsoft Graph API](https://docs.microsoft.com/en-us/graph/)

## Support

For issues specific to the Kubernetes deployment, check the application logs and Kubernetes events. For issues with the original Token Inventory functionality, refer to the [original repository](https://github.com/token2/token_inventory).
