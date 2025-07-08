require('dotenv').config();
const express = require('express');
const bodyParser = require('body-parser');
const axios = require('axios');
const https = require('https');
const cors = require('cors');

const axiosInstance = axios.create({
  httpsAgent: new https.Agent({ rejectUnauthorized: false })
});

const app = express();

// Configura CORS solo para el origen permitido en el .env
const allowedOrigin = process.env.MS_DNI_ALLOWED_ORIGIN;
app.use(cors({
  origin: allowedOrigin ? allowedOrigin : false,
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'x-api-key']
}));

// Cambia el orden: primero JSON, luego urlencoded
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Protección de microservicio con token de entorno
app.use((req, res, next) => {
  // Permitir que las preflight OPTIONS pasen sin autenticación
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  const token = req.headers['x-api-key'];
  if (!token || token !== process.env.MS_DNI_TOKEN) {
    return res.status(401).json({ success: false, message: 'No autorizado' });
  }
  next();
});

// Lista de funciones para consultar diferentes APIs de verificación de DNI
const dniApis = [
  // API local PHP
  async (dni) => {
    const domain = process.env.MS_DNI_DOMAIN;
    const backendPort = process.env.MS_DNI_BACKEND_PORT || '443';
    const response = await axiosInstance.post(`https://${domain}:${backendPort}/apk/verify_dni.php`, `dni=${dni}`, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    if (response.data && response.data.success) {
      return response.data;
    }
    throw new Error('verify_dni.php sin datos');
  },
  // API local api-proxy.php
  async (dni) => {
    const domain = process.env.MS_DNI_DOMAIN;
    const backendPort = process.env.MS_DNI_BACKEND_PORT || '443';
    const response = await axiosInstance.post(`https://${domain}:${backendPort}/apk/api-proxy.php`, `dni=${dni}`, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    if (response.data && response.data.success) {
      return response.data;
    }
    throw new Error('api-proxy.php sin datos');
  },
  // Puedes agregar más APIs aquí
];

// Endpoint para verificar DNI
app.post('/verificar-dni', async (req, res) => {
  console.log('BODY:', req.body); // Log para depuración
  const dni = req.body.dni || req.query.dni;
  if (!dni) {
    return res.json({ success: false, message: 'Debe enviar el DNI.' });
  }

  for (let i = 0; i < dniApis.length; i++) {
    try {
      const data = await dniApis[i](dni);
      return res.json({ success: true, api: i + 1, data });
    } catch (err) {
      // Si es la última API, responde error
      if (i === dniApis.length - 1) {
        return res.json({ success: false, message: 'No se pudo verificar el DNI en ninguna API.' });
      }
      // ...continúa con la siguiente API...
    }
  }
});

const port = process.env.MS_DNI_PORT;
app.listen(port, () => {
  console.log(`Microservicio de verificación de DNI escuchando en puerto ${port}`);
});


