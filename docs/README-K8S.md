# TOTP Token Inventory - Kubernetes Edition

This is a Kubernetes-ready fork of [Token2's TOTP Token Inventory](https://github.com/token2/token_inventory) with Azure AD authentication.

## What's New in This Fork?

✅ **Azure AD OAuth2 Authentication** - Secure login with your organization's Azure AD
✅ **Group-Based Access Control** - Restrict access to specific Azure AD groups
✅ **Kubernetes Deployment** - Scalable, containerized deployment
✅ **Multi-Replica Support** - High availability with load balancing
✅ **Docker Support** - Easy local development and testing
✅ **Production Ready** - Security best practices built-in

## Quick Start

### Local Development with Docker

1. **Copy environment file:**
   ```bash
   cp .env.example .env
   ```

2. **Configure Azure AD (see setup guide below)**

3. **Run with Docker Compose:**
   ```bash
   docker-compose up
   ```

4. **Access at:** http://localhost:8080

### Kubernetes Deployment

See [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md) for complete deployment guide.

**Quick deploy:**
```bash
# 1. Build and push image
docker build -t your-registry/token-inventory:latest .
docker push your-registry/token-inventory:latest

# 2. Configure secrets
nano k8s/secret.yaml

# 3. Deploy
kubectl apply -k k8s/

# Or use the deploy script
./deploy.sh
```

## Azure AD Setup

You need **TWO** Azure AD App Registrations:

### App Registration #1: OAuth (User Authentication)
- **Purpose:** Authenticate users to access the web application
- **Permissions:** `User.Read`, `openid`, `profile`, `email`
- **Optional:** `Group.Read.All` (for group-based access)
- **Redirect URI:** `https://your-domain.com/index.php`

### App Registration #2: Graph API (Token Management)
- **Purpose:** Manage hardware OATH tokens via Microsoft Graph
- **Permissions:**
  - `Policy.ReadWrite.AuthenticationMethod`
  - `UserAuthenticationMethod.ReadWrite.All`
  - `User.Read.All`
  - `Directory.Read.All`
- **Admin Consent:** Required

See [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md) for detailed setup instructions.

## Architecture

```
┌─────────────┐
│   Internet  │
└──────┬──────┘
       │
       ▼
┌─────────────────┐
│  Ingress (TLS)  │
└──────┬──────────┘
       │
       ▼
┌─────────────────┐
│    Service      │
└──────┬──────────┘
       │
       ▼
┌───────────────────────────┐
│  Deployment (2+ replicas) │
│  ┌─────────────────────┐  │
│  │  PHP Application    │  │
│  │  + Azure AD OAuth   │  │
│  └─────────────────────┘  │
└───────────────────────────┘
```

## Features

### From Original Project
- Hardware OATH token management via Microsoft Graph API
- Bulk import from CSV
- Token assignment and activation
- Self-service activation
- SHA-1 and SHA-256 support

### New in This Fork
- **Azure AD OAuth2 authentication** for secure access control
- **Group-based authorization** to limit access by AD group
- **Kubernetes manifests** for easy deployment
- **Docker support** for local development
- **Horizontal scaling** with multiple replicas
- **Health checks** and readiness probes
- **Security hardening** (non-root containers, security contexts)

## File Structure

```
token_inventory/
├── index.php                   # Main application (modified for web)
├── index-proxy.php            # Proxy version for corporate networks
├── azure-auth.php             # NEW: Azure AD OAuth implementation
├── Dockerfile                 # NEW: Container image definition
├── docker-compose.yml         # NEW: Local development setup
├── docker-entrypoint.sh       # NEW: Container startup script
├── apache-config.conf         # NEW: Apache configuration
├── deploy.sh                  # NEW: Quick deployment script
├── .env.example               # NEW: Environment template
├── KUBERNETES_DEPLOYMENT.md   # NEW: Detailed deployment guide
├── k8s/                       # NEW: Kubernetes manifests
│   ├── namespace.yaml
│   ├── secret.yaml
│   ├── deployment.yaml
│   ├── service.yaml
│   ├── ingress.yaml
│   └── kustomization.yaml
├── assets/                    # Frontend assets
└── README.md                  # This file
```

## Usage

1. **Deploy to Kubernetes** (see [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md))
2. **Access the web interface** via your configured domain
3. **Login with Azure AD** credentials
4. **Configure Graph API credentials** (App Registration #2)
5. **Import tokens** via CSV or JSON
6. **Assign and activate** tokens for users

## Security Features

- ✅ Azure AD OAuth2 authentication
- ✅ Group-based access control
- ✅ TLS/HTTPS required for production
- ✅ Non-root container execution
- ✅ Security contexts and capability dropping
- ✅ Secret management via Kubernetes secrets
- ✅ Session security (httpOnly, secure cookies)

## Configuration

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `AZURE_AD_TENANT_ID` | Azure AD tenant ID | Yes |
| `AZURE_AD_CLIENT_ID` | OAuth client ID (App Reg #1) | Yes |
| `AZURE_AD_CLIENT_SECRET` | OAuth client secret (App Reg #1) | Yes |
| `AZURE_AD_REDIRECT_URI` | OAuth redirect URI | Yes |
| `AZURE_AD_ALLOWED_GROUPS` | Comma-separated group IDs | No |

### Kubernetes Resources

- **Namespace:** `token-inventory`
- **Deployment:** 2 replicas (adjustable)
- **Service:** ClusterIP on port 80
- **Ingress:** Nginx (configurable)

## Scaling

Scale horizontally:
```bash
kubectl scale deployment token-inventory -n token-inventory --replicas=5
```

## Monitoring

View logs:
```bash
kubectl logs -n token-inventory -l app=token-inventory -f
```

Check health:
```bash
kubectl get pods -n token-inventory
kubectl describe pod <pod-name> -n token-inventory
```

## Troubleshooting

### Authentication Issues
- Verify redirect URI matches exactly in Azure AD
- Check client secret hasn't expired
- Ensure admin consent was granted

### Token Management Issues
- Verify Graph API credentials (App Registration #2)
- Check admin consent for Graph API permissions
- Review application logs

### Kubernetes Issues
```bash
# Check pod status
kubectl get pods -n token-inventory

# View detailed pod info
kubectl describe pod <pod-name> -n token-inventory

# Check logs
kubectl logs <pod-name> -n token-inventory

# Check events
kubectl get events -n token-inventory --sort-by='.lastTimestamp'
```

## Differences from Original

| Feature | Original | This Fork |
|---------|----------|-----------|
| Authentication | None | Azure AD OAuth2 |
| Deployment | PHP Desktop App | Kubernetes/Docker |
| Access Control | Open | Azure AD Groups |
| Scalability | Single instance | Multi-replica |
| Session Storage | Cookies only | Cookies + secure sessions |
| SSL/TLS | Optional | Required (recommended) |

## Contributing

This is a fork for Kubernetes deployment. For issues with the core Token Inventory functionality, please see the [original repository](https://github.com/token2/token_inventory).

For Kubernetes deployment issues:
1. Check the logs
2. Review [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md)
3. Open an issue with details

## Credits

- **Original Project:** [Token2 TOTP Token Inventory](https://github.com/token2/token_inventory)
- **Kubernetes Conversion:** This fork adds containerization and Azure AD auth

## License

See [LICENSE](LICENSE) for details.

## Additional Resources

- [Original Documentation](README-ORIGINAL.md)
- [Kubernetes Deployment Guide](KUBERNETES_DEPLOYMENT.md)
- [Microsoft Graph API Documentation](https://docs.microsoft.com/en-us/graph/)
- [Azure AD OAuth Documentation](https://docs.microsoft.com/en-us/azure/active-directory/develop/)
