# Sistema de Control de Incidencias

Aplicación web desarrollada como práctica individual para la asignatura **Infraestructura de Servicios**, utilizando Docker Compose para integrar cinco servicios independientes.

## 1. Descripción del proyecto

El Sistema de Control de Incidencias permite registrar y administrar problemas técnicos mediante una interfaz web.

La aplicación ofrece las siguientes funcionalidades:

- Consultar incidencias registradas.
- Crear incidencias con título, descripción y categoría.
- Editar los datos y el estado de una incidencia.
- Eliminar incidencias con confirmación.
- Consultar registros mediante paginación de cinco elementos por página.
- Utilizar Redis para almacenar temporalmente consultas y contabilizar las solicitudes.
- Generar notificaciones de prueba mediante Mailpit.

## 2. Tecnologías utilizadas

| Servicio | Tecnología | Función |
|---|---|---|
| frontend | Nginx y Tailwind CSS | Interfaz web y proxy inverso |
| backend | PHP 8.3 y Apache | API REST y lógica de negocio |
| db | MariaDB 11.4 | Almacenamiento persistente |
| redis | Redis 7 | Caché y contador de consultas |
| mailpit | Mailpit | Captura de notificaciones SMTP |

El frontend está desarrollado con HTML, JavaScript y Tailwind CSS v4. Tailwind se compila durante la construcción de la imagen Docker.

## 3. Arquitectura

Los cinco servicios se definen en `compose.yaml` y comparten la red Docker `app_network`.

El flujo de comunicación es:

```text
Navegador
   |
   v
Frontend (Nginx) - Puerto 8081
   |
   | /api/
   v
Backend (PHP + Apache)
   |
   +---- MariaDB (db:3306)
   |
   +---- Redis (redis:6379)
   |
   +---- Mailpit (mailpit:1025)
```

Nginx sirve los archivos estáticos y redirige las solicitudes `/api/` hacia el backend.

El backend utiliza los nombres de servicio proporcionados por Docker Compose para conectarse con MariaDB, Redis y Mailpit.

Los puertos del backend, MariaDB y Redis no se publican directamente hacia la computadora anfitriona.

## 4. Requisitos

Para ejecutar el proyecto se necesita:

- Docker Engine o Docker Desktop.
- Docker Compose v2.
- Git para clonar el repositorio.
- Navegador web.
- Conexión a Internet para descargar las imágenes y dependencias durante la primera construcción.

No es necesario instalar PHP, MariaDB, Redis, Node.js ni Tailwind CSS directamente en la computadora.

## 5. Instalación y ejecución

### 5.1. Clonar el repositorio

```bash
git clone https://github.com/sergio-cardona-04/proyecto-cardona-sergio.git
cd proyecto-cardona-sergio
```

El usuario debe contar con permisos de acceso al repositorio si este es privado.

### 5.2. Configurar las variables de entorno

Crear un archivo `.env` a partir de `.env.example`.

En PowerShell:

```powershell
Copy-Item .env.example .env
```

En Linux o macOS:

```bash
cp .env.example .env
```

Abrir `.env` y reemplazar los valores `REEMPLAZAR_PASSWORD` y `REEMPLAZAR_ROOT_PASSWORD` por contraseñas propias.

Las variables configurables incluyen:

| Variable | Descripción |
|---|---|
| DB_HOST | Nombre del servicio MariaDB |
| DB_PORT | Puerto interno de MariaDB |
| DB_NAME | Nombre de la base de datos |
| DB_USER | Usuario de la aplicación |
| DB_PASSWORD | Contraseña del usuario |
| DB_ROOT_PASSWORD | Contraseña de administración |
| REDIS_HOST | Nombre del servicio Redis |
| REDIS_PORT | Puerto interno de Redis |
| SMTP_HOST | Nombre del servicio Mailpit |
| SMTP_PORT | Puerto SMTP |
| MAIL_FROM | Correo remitente de prueba |
| MAIL_TO | Correo destinatario de prueba |

El archivo `.env` está excluido del control de versiones para evitar publicar credenciales.

### 5.3. Validar Docker Compose

```bash
docker compose config --quiet
```

Si la configuración es correcta, el comando termina sin mostrar errores.

### 5.4. Construir e iniciar los servicios

```bash
docker compose up -d --build
```

Docker Compose construirá las imágenes del frontend y backend, descargará las imágenes restantes, creará la red compartida y preparará los volúmenes.

Durante la construcción del frontend se instalan las dependencias de Tailwind y se genera el CSS que utiliza Nginx.

### 5.5. Verificar los contenedores

```bash
docker compose ps
```

Se deben mostrar los servicios `frontend`, `backend`, `db`, `redis` y `mailpit`.

MariaDB y Redis cuentan con verificaciones de salud. El backend también dispone de un healthcheck que comprueba la comunicación con ambas dependencias.

## 6. Direcciones de acceso

| Aplicación | URL |
|---|---|
| Sistema de incidencias | http://localhost:8081 |
| Interfaz de Mailpit | http://localhost:8025 |
| Endpoint de salud | http://localhost:8081/api/health |

Las direcciones corresponden a la computadora donde se ejecuta Docker Compose.

## 7. API REST

El backend proporciona los siguientes endpoints, accesibles mediante el proxy de Nginx:

| Método | Endpoint | Función |
|---|---|---|
| GET | `/api/incidencias` | Consultar el listado |
| POST | `/api/incidencias` | Crear una incidencia |
| PUT | `/api/incidencias/{id}` | Actualizar una incidencia |
| DELETE | `/api/incidencias/{id}` | Eliminar una incidencia |
| GET | `/api/health` | Comprobar MariaDB y Redis |

Las peticiones de creación y actualización utilizan JSON.

El backend valida los campos recibidos y devuelve respuestas con códigos HTTP adecuados.

## 8. Base de datos y persistencia

MariaDB utiliza la tabla `incidencias`, que contiene:

- `id`: identificador único.
- `titulo`: título de la incidencia.
- `descripcion`: descripción del problema.
- `categoria`: hardware, software, red u otro.
- `estado`: pendiente, en proceso o resuelta.
- `fecha_creacion`: fecha de registro.

El archivo `database/init.sql` crea la tabla y registra tres incidencias iniciales cuando se inicializa por primera vez una base de datos vacía.

La información se almacena en el volumen nombrado `mariadb_data`, montado en `/var/lib/mysql`.

Por ello, los registros se conservan al recrear contenedores o ejecutar:

```bash
docker compose down
docker compose up -d --build
```

El comando `docker compose down -v` elimina los volúmenes declarados, por lo que no debe utilizarse cuando se desea conservar la información.

## 9. Caché y contador con Redis

Redis almacena temporalmente los resultados del listado de incidencias durante 60 segundos.

Cada solicitud GET incrementa la clave `contador_consultas`, incluso cuando el resultado proviene de caché.

Cuando se crea, actualiza o elimina una incidencia, el backend incrementa `incidencias:version`. Esta versión forma parte de las claves de caché y permite evitar la reutilización de listados desactualizados.

Redis tiene habilitada la persistencia AOF y utiliza el volumen `redis_data`.

## 10. Paginación SQL

Como mejora individual, se implementó paginación real de registros.

El frontend consulta el endpoint utilizando parámetros como:

```text
/api/incidencias?pagina=2&limite=5
```

El backend ejecuta una consulta SQL con `LIMIT` y `OFFSET`, por lo que MariaDB devuelve únicamente los registros solicitados.

La interfaz muestra la página actual y permite navegar entre páginas.

## 11. Notificaciones con Mailpit

Al crear una incidencia, el backend genera automáticamente un correo de prueba mediante SMTP.

El mensaje contiene información del registro creado, como su ID, título, descripción, categoría, estado y fecha.

Los mensajes pueden consultarse en:

http://localhost:8025

Mailpit es una herramienta para desarrollo y pruebas; no realiza la entrega de correos reales a los destinatarios.

## 12. Comandos de verificación

Consultar los servicios:

```bash
docker compose ps
```

Consultar los registros del backend:

```bash
docker compose logs --tail=50 backend
```

Verificar el endpoint de salud:

```bash
curl http://localhost:8081/api/health
```

Acceder a MariaDB:

```bash
docker compose exec db mariadb -u incidencias_app -p incidencias_db
```

Dentro de MariaDB:

```sql
SELECT id, titulo, categoria, estado
FROM incidencias
ORDER BY id DESC;
```

Comprobar Redis:

```bash
docker compose exec redis redis-cli PING
docker compose exec redis redis-cli GET contador_consultas
docker compose exec redis redis-cli GET incidencias:version
```

Mostrar las claves de caché:

```bash
docker compose exec redis redis-cli --scan --pattern "incidencias:listado:*"
```

## 13. Detener y recuperar el proyecto

Detener los servicios sin eliminar los contenedores:

```bash
docker compose stop
```

Volver a iniciarlos:

```bash
docker compose start
```

Eliminar los contenedores y la red creada por Compose, conservando los volúmenes nombrados:

```bash
docker compose down
```

Volver a levantar la aplicación:

```bash
docker compose up -d --build
```

## 14. Estructura del repositorio

```text
proyecto-cardona-sergio/
├── backend/
│   ├── Dockerfile
│   ├── composer.json
│   ├── composer.lock
│   └── src/
├── database/
│   └── init.sql
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── package.json
│   ├── package-lock.json
│   └── src/
│       ├── index.html
│       ├── app.js
│       └── input.css
├── compose.yaml
├── .env.example
├── .gitignore
└── README.md
```

## 15. Consideraciones de seguridad

La aplicación fue desarrollada para un entorno académico local.

Se aplican medidas como el uso de variables de entorno, la exclusión de credenciales del repositorio, la separación de servicios mediante Docker Compose y la publicación únicamente de los puertos necesarios.

No se ha implementado autenticación de usuarios, por lo que el proyecto no debe publicarse directamente en Internet sin incorporar controles adicionales.

## 16. Conclusión

Este proyecto permitió implementar una aplicación multicontenedor mediante Docker Compose, integrando una interfaz web, una API REST, una base de datos persistente, un sistema de caché y un servicio de notificaciones.

La arquitectura permite administrar los servicios de forma independiente, conservar la información mediante volúmenes y establecer comunicación entre contenedores mediante nombres DNS internos.

La paginación SQL constituye la mejora individual implementada para optimizar la consulta y presentación de incidencias.