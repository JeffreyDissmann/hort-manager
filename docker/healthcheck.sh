#!/bin/sh
# Role-aware health probe (overrides the FrankenPHP base image's Caddy-admin probe).
case "${APP_ROLE:-web}" in
    queue)
        tr '\0' ' ' < /proc/1/cmdline | grep -q "queue:work"
        ;;
    scheduler)
        tr '\0' ' ' < /proc/1/cmdline | grep -q "schedule:work"
        ;;
    *)
        curl -fsS http://localhost:8080/up > /dev/null
        ;;
esac
