.PHONY: install dev build preview serve help

help:
	@echo ""
	@echo "  make install   npm install"
	@echo "  make dev       Vite dev server (puerto 5173)"
	@echo "  make build     Compilar frontend a dist/"
	@echo "  make preview   Previsualizar build"
	@echo "  make serve     PHP server en puerto 8000 (admin + api)"
	@echo ""

install:
	npm install

dev:
	npm run dev

build:
	npm run build

preview:
	npm run preview

serve:
	php -S localhost:8000 -t .
