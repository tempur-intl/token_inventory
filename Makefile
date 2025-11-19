.PHONY: help build push deploy test clean validate

# Configuration
REGISTRY ?= tempursealy
IMAGE_NAME ?= token-inventory
TAG ?= latest
NAMESPACE ?= token-inventory

help:
	@echo "Token Inventory - Makefile Commands"
	@echo ""
	@echo "Development:"
	@echo "  make test          - Run local development with docker-compose"
	@echo "  make validate      - Validate setup and configuration"
	@echo ""
	@echo "Docker:"
	@echo "  make build         - Build Docker image"
	@echo "  make push          - Push Docker image to registry"
	@echo "  make run           - Run container locally"
	@echo ""
	@echo "Kubernetes:"
	@echo "  make deploy        - Deploy to Kubernetes"
	@echo "  make status        - Check deployment status"
	@echo "  make logs          - View application logs"
	@echo "  make shell         - Open shell in pod"
	@echo "  make delete        - Delete from Kubernetes"
	@echo ""
	@echo "Configuration:"
	@echo "  REGISTRY=$(REGISTRY)"
	@echo "  IMAGE_NAME=$(IMAGE_NAME)"
	@echo "  TAG=$(TAG)"
	@echo "  NAMESPACE=$(NAMESPACE)"

# Development
test:
	docker-compose up

validate:
	./validate-setup.sh

# Docker operations
build:
	docker build -t $(REGISTRY)/$(IMAGE_NAME):$(TAG) .

push: build
	docker push $(REGISTRY)/$(IMAGE_NAME):$(TAG)

run:
	docker run -p 8080:80 \
		--env-file .env \
		$(REGISTRY)/$(IMAGE_NAME):$(TAG)

# Kubernetes operations
deploy: push
	cd k8s && \
	sed -i.bak "s|image: .*|image: $(REGISTRY)/$(IMAGE_NAME):$(TAG)|g" deployment.yaml && \
	kubectl apply -k . && \
	kubectl rollout status deployment/$(IMAGE_NAME) -n $(NAMESPACE)

status:
	kubectl get all -n $(NAMESPACE)
	@echo ""
	@echo "Ingress:"
	kubectl get ingress -n $(NAMESPACE)

logs:
	kubectl logs -n $(NAMESPACE) -l app=$(IMAGE_NAME) --tail=100 -f

shell:
	kubectl exec -it -n $(NAMESPACE) deployment/$(IMAGE_NAME) -- /bin/bash

delete:
	kubectl delete namespace $(NAMESPACE)

# Cleanup
clean:
	docker-compose down -v
	docker rmi $(REGISTRY)/$(IMAGE_NAME):$(TAG) || true
	rm -f k8s/*.bak
