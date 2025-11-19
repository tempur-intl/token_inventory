#!/bin/bash
# Quick build and deployment script for Kubernetes

set -e

# Configuration - UPDATE THESE
REGISTRY="your-registry"
IMAGE_NAME="token-inventory"
TAG="${1:-latest}"
NAMESPACE="token-inventory"

echo "========================================"
echo "Token Inventory - Kubernetes Deployment"
echo "========================================"
echo ""

# Check if kubectl is available
if ! command -v kubectl &> /dev/null; then
    echo "ERROR: kubectl is not installed or not in PATH"
    exit 1
fi

# Check if docker is available
if ! command -v docker &> /dev/null; then
    echo "ERROR: docker is not installed or not in PATH"
    exit 1
fi

echo "Step 1: Building Docker image..."
docker build -t ${REGISTRY}/${IMAGE_NAME}:${TAG} .

echo ""
echo "Step 2: Pushing Docker image to registry..."
docker push ${REGISTRY}/${IMAGE_NAME}:${TAG}

echo ""
echo "Step 3: Updating Kubernetes deployment..."

# Update the image in deployment.yaml
cd k8s
sed -i.bak "s|image: .*|image: ${REGISTRY}/${IMAGE_NAME}:${TAG}|g" deployment.yaml

echo ""
echo "Step 4: Applying Kubernetes manifests..."

# Check if namespace exists
if ! kubectl get namespace ${NAMESPACE} &> /dev/null; then
    echo "Creating namespace ${NAMESPACE}..."
    kubectl apply -f namespace.yaml
fi

# Apply all manifests
kubectl apply -f secret.yaml
kubectl apply -f deployment.yaml
kubectl apply -f service.yaml
kubectl apply -f ingress.yaml

echo ""
echo "Step 5: Waiting for deployment to be ready..."
kubectl rollout status deployment/token-inventory -n ${NAMESPACE} --timeout=300s

echo ""
echo "========================================"
echo "Deployment Complete!"
echo "========================================"
echo ""
echo "Check status with:"
echo "  kubectl get pods -n ${NAMESPACE}"
echo "  kubectl logs -n ${NAMESPACE} -l app=token-inventory"
echo ""
echo "Access the application at:"
kubectl get ingress -n ${NAMESPACE}
