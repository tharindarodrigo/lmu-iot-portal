#!/bin/bash

set -euo pipefail

PATH="/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin:${PATH:-}"

usage() {
    cat <<'EOF'
Usage: ./scripts/node-red-cloudflare-link.sh [--managed]

Starts local Node-RED and exposes it through Cloudflare.

Modes:
  default     Start a temporary Cloudflare Quick Tunnel and print a public URL.
              Use this when you need a disposable URL for production forwarding.

  --managed   Start the long-lived Cloudflare tunnel defined in compose.yaml.
              Requires CLOUDFLARE_TUNNEL_TOKEN in .env.

After Cloudflare prints a hostname, point production traffic to:
  https://<hostname>/migration/legacy-ingest
EOF
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

mode="quick"

for arg in "$@"; do
    case "$arg" in
        --managed)
            mode="managed"
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $arg" >&2
            usage
            exit 1
            ;;
    esac
done

script_dir="$(cd "${BASH_SOURCE[0]%/*}" && pwd)"
repo_root="$(cd "$script_dir/.." && pwd)"
compose_file="$repo_root/compose.yaml"
env_file="$repo_root/.env"

if ! command_exists docker; then
    echo "Error: docker is required." >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    echo "Error: docker compose is required." >&2
    exit 1
fi

if [[ ! -f "$compose_file" ]]; then
    echo "Error: compose file not found at $compose_file" >&2
    exit 1
fi

echo "Starting local Node-RED..."
(cd "$repo_root" && docker compose -f compose.yaml up -d node-red)

echo
echo "Public ingest path: /migration/legacy-ingest"
echo "Local Node-RED editor: http://localhost:${FORWARD_NODE_RED_PORT:-1880}"
echo

if [[ "$mode" == "managed" ]]; then
    if [[ ! -f "$env_file" ]]; then
        echo "Error: .env file not found. Create it first so the Cloudflare tunnel token can be loaded." >&2
        exit 1
    fi

    if ! grep -Eq '^CLOUDFLARE_TUNNEL_TOKEN=.+$' "$env_file"; then
        echo "Error: CLOUDFLARE_TUNNEL_TOKEN is missing in .env." >&2
        exit 1
    fi

    echo "Starting managed Cloudflare tunnel..."
    (cd "$repo_root" && docker compose -f compose.yaml up -d cloudflared)
    echo
    echo "Managed tunnel is running. Use the Cloudflare Zero Trust hostname already attached to this tunnel and append:"
    echo "  /migration/legacy-ingest"
    exit 0
fi

echo "Starting a temporary Cloudflare Quick Tunnel..."
echo "Copy the generated trycloudflare.com URL and append /migration/legacy-ingest"
echo "Press Ctrl+C when you are done forwarding production traffic."
echo

(cd "$repo_root" && docker compose -f compose.yaml run --rm --no-deps cloudflared tunnel --no-autoupdate --url http://node-red:1880)