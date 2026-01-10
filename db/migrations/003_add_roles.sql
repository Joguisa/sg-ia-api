-- Ejecutar en MySQL
USE sg_ia_db;

-- Agregar columna de rol
ALTER TABLE admins 
ADD COLUMN role ENUM('superadmin', 'admin') NOT NULL DEFAULT 'admin' AFTER password_hash;

-- Actualizar a tu usuario principal como superadmin
-- (Ajusta el email si es diferente al de las semillas)
UPDATE admins SET role = 'superadmin' WHERE email = 'admin@sg-ia.com';

-- Índice para búsquedas rápidas por rol (útil para el Superadmin)
ALTER TABLE admins ADD KEY ix_admins_role (role);