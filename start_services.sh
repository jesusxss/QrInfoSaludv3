#!/bin/bash
# Script para producción: inicia ambos microservicios Node.js y los mantiene activos con PM2
# Uso: ./start_services.sh

# Inicia el microservicio de autenticación
echo "Iniciando microservicio de autenticación..."
pm2 start auth_microservice_node.js --name auth

# Inicia el microservicio de verificación de DNI
echo "Iniciando microservicio de verificación de DNI..."
pm2 start dni_verification_microservice.js --name dni

# Guarda el estado de PM2 para reinicio automático
echo "Guardando configuración de PM2 para reinicio automático..."
pm2 save

echo "Ambos microservicios están corriendo bajo PM2. Usa 'pm2 list' para ver el estado."
