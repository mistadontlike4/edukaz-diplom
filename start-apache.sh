#!/usr/bin/env bash
set -e

echo "== Force single MPM (prefork) =="

# Отключаем все MPM на всякий случай
a2dismod mpm_event mpm_worker mpm_prefork >/dev/null 2>&1 || true

# Удаляем возможные включённые файлы модулей (если остались)
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* /etc/apache2/mods-enabled/mpm_prefork.* || true

# Включаем только prefork
a2enmod mpm_prefork >/dev/null 2>&1 || true

echo "Enabled MPM:"
apache2ctl -M | grep mpm || true

# Проверка конфига
apache2ctl -t

# Запуск Apache в foreground (как в официальном образе)
exec apache2-foreground