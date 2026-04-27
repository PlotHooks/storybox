#!/usr/bin/env bash
set -e

echo "Installing Codex CLI..."
npm install -g @openai/codex

echo "Codex installed:"
codex --version || true

