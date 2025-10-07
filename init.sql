-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Окт 02 2025 г., 20:39
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12


-- PostgreSQL init script
BEGIN;

--
-- База данных: `edukaz`
--

-- --------------------------------------------------------

--
-- Структура таблицы `files`
--


CREATE TYPE access_type_enum AS ENUM ('public', 'private', 'user');
CREATE TABLE files (
  id SERIAL PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  size BIGINT NOT NULL DEFAULT 0,
  downloads INTEGER NOT NULL DEFAULT 0,
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  uploaded_by INTEGER NOT NULL,
  access_type access_type_enum NOT NULL DEFAULT 'public',
  shared_with INTEGER,
  uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

--
-- Дамп данных таблицы `files`
--

INSERT INTO files (id, filename, size, downloads, original_name, uploaded_by, access_type, shared_with, uploaded_at) VALUES
(4, 'svezevypecennaa-picca-margarita-s-syrom-mocarella-i-list-ami-bazilika.jpg', 3615535, 1, '', 2, 'private', NULL, '2025-10-01 20:43:44'),
(6, 'задания на Пайтон без ссылки.docx', 17539, 0, '', 1, 'user', 2, '2025-10-01 20:57:30'),
(7, 'лекция (3).pptx', 822905, 0, '', 4, 'user', 1, '2025-10-02 04:30:51'),
(8, 'конспект лекций Охрана труда.doc', 548352, 0, '', 1, 'user', 2, '2025-10-02 07:20:43'),
(9, 'конспект лекций Охрана труда (1).doc', 548352, 0, '', 1, 'user', 2, '2025-10-02 07:20:54');

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--


CREATE TABLE roles (
  id SERIAL PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL
);

--
-- Дамп данных таблицы `roles`
--

INSERT INTO roles (id, role_name) VALUES
(1, 'admin'),
(2, 'user');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--


CREATE TYPE user_role_enum AS ENUM ('admin', 'user');
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role user_role_enum DEFAULT 'user',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

--
-- Дамп данных таблицы `users`
--

INSERT INTO users (id, username, email, password, role, created_at) VALUES
(1, 'admin', 'admin@edukaz.local', '$2y$10$TWCQHeH.P8UYOECLvblqgeXrFCCYXI6YDgeqobEpbMb6649PA5h7m', 'admin', '2025-10-01 20:20:59'),
(2, 'san', 'zhanabayev_s@mail.ru', '$2y$10$LezA9f2QX0BxPym5ve0ZCuQaCRoTJrvajTnsUg28OnXV6InIqP.72', 'user', '2025-10-01 20:22:51'),
(3, 'user', 'user@local.com', '$2y$10$5ziSa830N6TykGEeFJNFFuDTbAfi34sAlHS30lVwAv4nr0cVJgOMO', 'user', '2025-10-01 20:44:40'),
(4, 'galamtor', 'addd@mail.rui', '$2y$10$7D/81htPL1Z3j2Z/odspXeazWhgghlsxX1sNMKmq9h0kGFI7CbJBu', 'user', '2025-10-02 04:30:16');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `files`
--
CREATE INDEX idx_files_uploaded_by ON files(uploaded_by);
CREATE INDEX idx_files_shared_with ON files(shared_with);

--
-- Индексы таблицы `roles`
--
-- PRIMARY KEY уже задан в CREATE TABLE

--
-- Индексы таблицы `users`
--
-- PRIMARY KEY и UNIQUE уже заданы в CREATE TABLE

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `files`
--
-- SERIAL уже задаёт автоинкремент

--
-- AUTO_INCREMENT для таблицы `roles`
--
-- SERIAL уже задаёт автоинкремент

--
-- AUTO_INCREMENT для таблицы `users`
--
-- SERIAL уже задаёт автоинкремент

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `files`
--
ALTER TABLE files
  ADD CONSTRAINT files_uploaded_by_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
  ADD CONSTRAINT files_shared_with_fk FOREIGN KEY (shared_with) REFERENCES users(id) ON DELETE SET NULL;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
