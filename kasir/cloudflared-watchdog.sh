#!/bin/bash
LOG=/var/log/cloudflared-watchdog.log
ACTIVE=$(systemctl is-active cloudflared 2>/dev/null)
if [ "$ACTIVE" != "active" ]; then
  echo "$(date '+%F %T') cloudflared NOT active ($ACTIVE) -> restart" >> "$LOG"
  systemctl restart cloudflared
  exit 0
fi
REG=$(journalctl -u cloudflared --since "12 minutes ago" --no-pager 2>/dev/null | grep -c "Registered tunnel connection")
if [ "$REG" -eq 0 ]; then
  echo "$(date '+%F %T') tunnel tidak terdaftar 12 mnt -> restart" >> "$LOG"
  systemctl restart cloudflared
fi