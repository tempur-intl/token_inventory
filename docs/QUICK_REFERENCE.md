# Quick Reference Guide

## 🚀 Quick Start Commands

### Local Development
```bash
# 1. Copy environment file
cp .env.example .env

# 2. Edit .env with your Azure AD credentials
nano .env

# 3. Start with Docker Compose
docker-compose up

# 4. Access at http://localhost:8080
```

### Kubernetes Deployment
```bash
# 1. Configure secrets
nano k8s/secret.yaml

# 2. Update deployment with your registry
nano k8s/deployment.yaml

# 3. Build and push
docker build -t your-registry/token-inventory:latest .
docker push your-registry/token-inventory:latest

# 4. Deploy
kubectl apply -k k8s/

# 5. Check status
kubectl get pods -n token-inventory
```

## 📋 Required Azure AD Setup

### App Registration #1: OAuth (User Authentication)
```
Purpose: Protect the web application
Redirect URI: https://your-domain.com/index.php
Permissions:
  - User.Read
  - openid
  - profile
  - email
  - Group.Read.All (optional, for group filtering)
```

### App Registration #2: Graph API (Token Management)
```
Purpose: Manage hardware OATH tokens
Permissions (with admin consent):
  - Policy.ReadWrite.AuthenticationMethod
  - UserAuthenticationMethod.ReadWrite.All
  - User.Read.All
  - Directory.Read.All
```

## 🔑 Environment Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `AZURE_AD_TENANT_ID` | Your Azure AD tenant ID | `12345678-1234-...` |
| `AZURE_AD_CLIENT_ID` | OAuth client ID (App Reg #1) | `abcdef12-3456-...` |
| `AZURE_AD_CLIENT_SECRET` | OAuth client secret | `secret~value~here` |
| `AZURE_AD_REDIRECT_URI` | OAuth callback URL | `https://domain.com/index.php` |
| `AZURE_AD_ALLOWED_GROUPS` | Group IDs (comma-separated) | `group1,group2` |

## 🛠️ Common Commands

### Docker
```bash
# Build image
docker build -t token-inventory:latest .

# Run locally
docker run -p 8080:80 --env-file .env token-inventory:latest

# View logs
docker logs <container-id>

# Shell access
docker exec -it <container-id> /bin/bash
```

### Docker Compose
```bash
# Start
docker-compose up

# Start in background
docker-compose up -d

# Stop
docker-compose down

# View logs
docker-compose logs -f

# Rebuild
docker-compose up --build
```

### Kubernetes
```bash
# Deploy
kubectl apply -k k8s/

# Check status
kubectl get all -n token-inventory

# View pods
kubectl get pods -n token-inventory

# View logs
kubectl logs -n token-inventory -l app=token-inventory -f

# Describe pod
kubectl describe pod <pod-name> -n token-inventory

# Shell into pod
kubectl exec -it <pod-name> -n token-inventory -- /bin/bash

# Scale
kubectl scale deployment token-inventory -n token-inventory --replicas=3

# Restart
kubectl rollout restart deployment/token-inventory -n token-inventory

# Delete
kubectl delete namespace token-inventory
```

### Makefile (if you prefer)
```bash
# See all commands
make help

# Local testing
make test

# Validate setup
make validate

# Build and push
make build push

# Deploy to K8s
make deploy

# Check status
make status

# View logs
make logs
```

## 🔍 Troubleshooting

### Authentication Issues
```bash
# Check redirect URI
echo "Redirect URI must match exactly in Azure AD"

# Check secrets
kubectl get secret token-inventory-secrets -n token-inventory -o yaml

# Check logs for auth errors
kubectl logs -n token-inventory -l app=token-inventory | grep -i auth
```

### Deployment Issues
```bash
# Check pod status
kubectl get pods -n token-inventory

# View detailed info
kubectl describe pod <pod-name> -n token-inventory

# Check events
kubectl get events -n token-inventory --sort-by='.lastTimestamp'

# Check image pull
kubectl describe pod <pod-name> -n token-inventory | grep -A 5 "Events"
```

### Network Issues
```bash
# Check service
kubectl get svc -n token-inventory

# Check ingress
kubectl get ingress -n token-inventory

# Test from within cluster
kubectl run test-pod --rm -it --image=curlimages/curl -- /bin/sh
curl http://token-inventory.token-inventory.svc.cluster.local
```

## 📁 File Reference

| File | Purpose |
|------|---------|
| `azure-auth.php` | Azure AD authentication |
| `index.php` | Main application |
| `Dockerfile` | Container definition |
| `docker-compose.yml` | Local dev environment |
| `.env` | Local configuration |
| `k8s/secret.yaml` | K8s secrets (Azure AD config) |
| `k8s/deployment.yaml` | K8s deployment |
| `k8s/ingress.yaml` | K8s ingress (domain/TLS) |
| `deploy.sh` | Quick deployment script |
| `validate-setup.sh` | Setup validation |
| `Makefile` | Common commands |

## 🔒 Security Checklist

- [ ] Azure AD app registrations created
- [ ] Admin consent granted for Graph API permissions
- [ ] Client secrets stored securely (not in Git)
- [ ] TLS/HTTPS enabled in production
- [ ] Group-based access control configured (if needed)
- [ ] K8s secrets properly configured
- [ ] Container runs as non-root
- [ ] Network policies applied (optional)
- [ ] Regular security updates scheduled

## 🌐 Access Flow

1. User navigates to `https://your-domain.com`
2. Application checks for Azure AD session
3. If not logged in → Redirect to Azure AD
4. User authenticates with Azure AD
5. Azure AD redirects back with auth code
6. Application exchanges code for token
7. Application checks group membership (if configured)
8. User accesses application
9. User configures Graph API credentials
10. User manages OATH tokens

## 📚 Documentation Links

- [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md) - Full deployment guide
- [README-K8S.md](README-K8S.md) - Overview and features
- [CONVERSION_SUMMARY.md](CONVERSION_SUMMARY.md) - What changed
- [Original README](README.md) - Token Inventory documentation

## 🆘 Getting Help

1. Check logs: `kubectl logs -n token-inventory -l app=token-inventory`
2. Validate setup: `./validate-setup.sh`
3. Review documentation: [KUBERNETES_DEPLOYMENT.md](KUBERNETES_DEPLOYMENT.md)
4. Check Azure AD app registration settings
5. Verify Graph API permissions and admin consent

## ✅ Pre-Flight Checklist

Before deployment:
- [ ] Docker installed and running
- [ ] kubectl installed and configured
- [ ] Kubernetes cluster accessible
- [ ] Container registry accessible
- [ ] Azure AD app registrations created
- [ ] Admin consent granted
- [ ] Domain name configured
- [ ] DNS pointing to ingress
- [ ] TLS certificate ready
- [ ] `.env` configured (local) or `k8s/secret.yaml` (K8s)
- [ ] Ingress controller installed (nginx, traefik, etc.)

## 🎯 Success Indicators

You know it's working when:
- ✅ Pods are running: `kubectl get pods -n token-inventory`
- ✅ Service is accessible: `kubectl get svc -n token-inventory`
- ✅ Ingress has an address: `kubectl get ingress -n token-inventory`
- ✅ Azure AD login redirects properly
- ✅ You can access the web interface
- ✅ Graph API credentials can be configured
- ✅ Tokens can be imported and managed
