# Atajos para "Recordatorio de Tareas".
# Ejecuta `make` o `make help` para ver los comandos disponibles.

# Credenciales de la BD para los tests de integración (sobreescribibles):
#   make test DB_PASS=root
DB_HOST ?= 127.0.0.1
DB_NAME ?= recordatorio_tareas
DB_USER ?= root
DB_PASS ?=

.DEFAULT_GOAL := help

.PHONY: help install serve css test test-unit test-integration lint \
        up down logs seed config key

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

install: ## Instala las dependencias de Composer (incluye dev)
	composer install

serve: ## Arranca el servidor de desarrollo en http://localhost:8000
	php -S localhost:8000 -t public

css: ## Compila el SCSS a CSS (requiere sass)
	sass public/assets/style.scss public/assets/style.css

test: ## Ejecuta TODA la suite de tests (unitarios + integración)
	DB_HOST=$(DB_HOST) DB_USER=$(DB_USER) DB_PASS=$(DB_PASS) vendor/bin/phpunit

test-unit: ## Ejecuta solo los tests unitarios (sin BD)
	vendor/bin/phpunit --testsuite Unitarios

test-integration: ## Ejecuta solo los tests de integración (requiere BD)
	DB_HOST=$(DB_HOST) DB_USER=$(DB_USER) DB_PASS=$(DB_PASS) vendor/bin/phpunit --testsuite Integracion

lint: ## Comprueba la sintaxis de todos los archivos PHP
	@find lib public tests docker -name '*.php' -print0 | xargs -0 -n1 php -l

up: ## Levanta el entorno Docker (db + app + phpMyAdmin)
	docker compose up --build

down: ## Detiene el entorno Docker y borra sus datos
	docker compose down -v

logs: ## Muestra los logs de los contenedores
	docker compose logs -f

seed: ## Carga los datos de ejemplo en la BD (make seed DB_PASS=...)
	mysql -h $(DB_HOST) -u $(DB_USER) $(if $(DB_PASS),-p$(DB_PASS),) $(DB_NAME) < sql/datos_ejemplo.sql

config: ## Crea config.php a partir del ejemplo (si no existe)
	@test -f config.php && echo "config.php ya existe." \
		|| (cp config.example.php config.php && echo "config.php creado. Edítalo con tus datos.")

key: ## Genera una app_key (base64 de 32 bytes)
	@php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
