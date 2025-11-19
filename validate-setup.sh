#!/bin/bash
# Quick test script to validate the setup

echo "========================================"
echo "Token Inventory - Setup Validation"
echo "========================================"
echo ""

# Check Docker
echo "Checking Docker..."
if command -v docker &> /dev/null; then
    echo "✅ Docker is installed: $(docker --version)"
else
    echo "❌ Docker is not installed"
fi
echo ""

# Check kubectl
echo "Checking kubectl..."
if command -v kubectl &> /dev/null; then
    echo "✅ kubectl is installed: $(kubectl version --client --short 2>/dev/null || kubectl version --client)"

    # Check cluster connection
    if kubectl cluster-info &> /dev/null; then
        echo "✅ kubectl can connect to cluster"
    else
        echo "⚠️  kubectl cannot connect to cluster (this is OK for local development)"
    fi
else
    echo "❌ kubectl is not installed"
fi
echo ""

# Check if .env file exists
echo "Checking configuration..."
if [ -f .env ]; then
    echo "✅ .env file exists"

    # Check if it has values
    if grep -q "your-tenant-id" .env 2>/dev/null; then
        echo "⚠️  .env file still has placeholder values - please configure it"
    else
        echo "✅ .env file appears configured"
    fi
else
    echo "⚠️  .env file not found - copy from .env.example"
fi
echo ""

# Check if secret.yaml is configured
echo "Checking Kubernetes secrets..."
if [ -f k8s/secret.yaml ]; then
    if grep -q "your-tenant-id" k8s/secret.yaml 2>/dev/null; then
        echo "⚠️  k8s/secret.yaml has placeholder values - please configure it"
    else
        echo "✅ k8s/secret.yaml appears configured"
    fi
else
    echo "❌ k8s/secret.yaml not found"
fi
echo ""

# Check required files
echo "Checking required files..."
REQUIRED_FILES=(
    "index.php"
    "index-proxy.php"
    "azure-auth.php"
    "ldap-auth.php"
    "auth-router.php"
    "Dockerfile"
    "docker-compose.yml"
    "k8s/namespace.yaml"
    "k8s/deployment.yaml"
    "k8s/service.yaml"
    "k8s/ingress.yaml"
)

ALL_PRESENT=true
for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file"
        ALL_PRESENT=false
    fi
done
echo ""

# Summary
echo "========================================"
echo "Summary"
echo "========================================"

if $ALL_PRESENT; then
    echo "✅ All required files are present"
else
    echo "❌ Some files are missing"
fi
echo ""

echo "Next steps:"
echo "1. Choose authentication method (Azure AD or Local AD)"
echo "2. Configure .env for local development (copy from .env.example)"
echo "3. Configure k8s/secret.yaml for Kubernetes deployment"
echo "4. Set AUTH_METHOD to 'azure' or 'ldap' in your configuration"
echo "5. Configure the appropriate auth settings (Azure AD or LDAP)"
echo "6. Test locally: docker-compose up"
echo "7. Deploy to Kubernetes: ./deploy.sh"
echo ""
echo "Documentation:"
echo "- Azure AD setup: KUBERNETES_DEPLOYMENT.md"
echo "- LDAP setup: LDAP_AUTHENTICATION.md"
echo "- Overview: AUTHENTICATION_OPTIONS.md"
