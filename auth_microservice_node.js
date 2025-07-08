require('dotenv').config();
const express = require('express');
const bodyParser = require('body-parser');
const mysql = require('mysql2');
const bcrypt = require('bcrypt');
const cors = require('cors');
const https = require('https');
const axios = require('axios');

const app = express();

// Configura CORS solo para el origen permitido en el .env
const allowedOrigin = process.env.MS_AUTH_ALLOWED_ORIGIN;
app.use(cors({
  origin: allowedOrigin ? allowedOrigin : false,
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'x-api-key']
}));

// Protección de microservicio con token de entorno
app.use((req, res, next) => {
  // Permitir que las preflight OPTIONS pasen sin autenticación
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  const token = req.headers['x-api-key'];
  if (!token || token !== process.env.MS_AUTH_TOKEN) {
    return res.status(401).json({ success: false, message: 'No autorizado' });
  }
  next();
});

// Cambia el orden: primero JSON, luego urlencoded
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

const conn = mysql.createConnection({
  host: process.env.MS_AUTH_DB_HOST,
  user: process.env.MS_AUTH_DB_USER,
  password: process.env.MS_AUTH_DB_PASS,
  database: process.env.MS_AUTH_DB_NAME
});

// Crear instancia de axios con httpsAgent que ignora certificados no válidos
const axiosInstance = axios.create({
  httpsAgent: new https.Agent({ rejectUnauthorized: false })
});

// Endpoint de login
app.post('/auth', (req, res) => {
  console.log('BODY:', req.body); // Log para depuración
  const correo = req.body.correo ? req.body.correo.trim() : '';
  const contrasena = req.body['contraseña'] || req.body['contrasena'] || '';

  if (!correo || !contrasena) {
    return res.json({ success: false, message: 'Por favor ingrese correo y contraseña.' });
  }

  conn.query(
    'SELECT id, nombre_usuario, correo, contrasena FROM usuarios WHERE correo = ?',
    [correo],
    (err, results) => {
      if (err) return res.json({ success: false, message: 'Error de base de datos.' });
      if (results.length === 0) {
        return res.json({ success: false, message: 'El correo no está registrado.' });
      }
      const user = results[0];
      let hash = Buffer.isBuffer(user.contrasena) ? user.contrasena.toString() : String(user.contrasena);
      if (hash.startsWith('$2y$')) {
        hash = '$2b$' + hash.substring(4);
      }
      bcrypt.compare(contrasena, hash, (err, match) => {
        if (err) {
          return res.json({ success: false, message: 'Error al verificar la contraseña.' });
        }
        if (match) {
          res.json({
            success: true,
            message: 'Autenticación exitosa.',
            user: {
              id: user.id,
              nombre_usuario: user.nombre_usuario,
              correo: user.correo
            }
          });
        } else {
          res.json({ success: false, message: 'Contraseña incorrecta.' });
        }
      });
    }
  );
});

// Endpoint de registro
app.post('/register', (req, res) => {
  console.log('BODY:', req.body); // Log para depuración
  const nombre_usuario = req.body.nombre_usuario ? req.body.nombre_usuario.trim() : '';
  const correo = req.body.correo ? req.body.correo.trim() : '';
  const contrasena = req.body['contrasena'] || req.body['contraseña'] || '';

  if (!nombre_usuario || !correo || !contrasena) {
    return res.json({ success: false, message: 'Todos los campos son obligatorios.' });
  }

  // Solo verifica si el correo ya está registrado
  conn.query(
    'SELECT id FROM usuarios WHERE correo = ?',
    [correo],
    (err, results) => {
      if (err) return res.json({ success: false, message: 'Error de base de datos.' });
      if (results.length > 0) {
        return res.json({ success: false, message: 'El correo electrónico ya está registrado.' });
      }
      // Permite nombres de usuario repetidos, solo correo debe ser único
      bcrypt.hash(contrasena, 10, (err, hash) => {
        if (err) return res.json({ success: false, message: 'Error al cifrar la contraseña.' });
        conn.query(
          'INSERT INTO usuarios (nombre_usuario, correo, contrasena) VALUES (?, ?, ?)',
          [nombre_usuario, correo, hash],
          (err, result) => {
            if (err) {
              // Si el error es por correo duplicado, muestra mensaje claro
              if (err.code === 'ER_DUP_ENTRY') {
                return res.json({ success: false, message: 'El correo electrónico ya está registrado.' });
              }
              return res.json({ success: false, message: 'No se pudo registrar el usuario. Intente nuevamente.' });
            }
            res.json({ success: true, message: 'Registro exitoso.', user_id: result.insertId });
          }
        );
      });
    }
  );
});

const port = process.env.MS_AUTH_PORT;
app.listen(port, () => {
  console.log(`Microservicio Node.js de autenticación escuchando en puerto ${port}`);
});

// Si tienes llamadas a APIs internas con axios, reemplaza axios.post por axiosInstance.post
