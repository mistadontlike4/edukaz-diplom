-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Окт 02 2025 г., 20:39
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `edukaz`
--

-- --------------------------------------------------------

--
-- Структура таблицы `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `size` bigint(20) NOT NULL DEFAULT 0,
  `downloads` int(11) NOT NULL DEFAULT 0,
  `original_name` varchar(255) NOT NULL DEFAULT '',
  `uploaded_by` int(11) NOT NULL,
  `access_type` enum('public','private','user') NOT NULL DEFAULT 'public',
  `shared_with` int(11) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `files`
--

INSERT INTO `files` (`id`, `filename`, `size`, `downloads`, `original_name`, `uploaded_by`, `access_type`, `shared_with`, `uploaded_at`) VALUES
(4, 'svezevypecennaa-picca-margarita-s-syrom-mocarella-i-list-ami-bazilika.jpg', 3615535, 1, '', 2, 'private', NULL, '2025-10-01 20:43:44'),
(6, 'задания на Пайтон без ссылки.docx', 17539, 0, '', 1, '', 2, '2025-10-01 20:57:30'),
(7, 'лекция (3).pptx', 822905, 0, '', 4, '', 1, '2025-10-02 04:30:51'),
(8, 'конспект лекций Охрана труда.doc', 548352, 0, '', 1, '', 2, '2025-10-02 07:20:43'),
(9, 'конспект лекций Охрана труда (1).doc', 548352, 0, '', 1, '', 2, '2025-10-02 07:20:54');

-- --------------------------------------------------------

--
-- Структура таблицы `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `roles`
--

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'admin'),
(2, 'user');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`) VALUES
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
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_files_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_files_shared_with` (`shared_with`);

--
-- Индексы таблицы `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uniq_users_username` (`username`),
  ADD UNIQUE KEY `uniq_users_email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT для таблицы `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`shared_with`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
