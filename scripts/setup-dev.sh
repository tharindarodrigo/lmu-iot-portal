#!/bin/bash

# Setup script for LMU IoT Portal development environment
# Configures git hooks and commit message templates

set -e

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}🔧 Setting up LMU IoT Portal development environment...${NC}"
echo ""

# 1. Configure git to use custom hooks directory
echo -e "${BLUE}📂 Configuring git hooks directory...${NC}"
git config core.hooksPath .githooks
echo -e "${GREEN}✅ Git hooks configured${NC}"

# 2. Set commit message template
echo -e "${BLUE}📝 Setting commit message template...${NC}"
git config commit.template .gitmessage
echo -e "${GREEN}✅ Commit template configured${NC}"

# 3. Make scripts executable (if not already)
echo -e "${BLUE}🔐 Setting executable permissions...${NC}"
chmod +x scripts/new-feature.sh
chmod +x .githooks/commit-msg
chmod +x .githooks/prepare-commit-msg
echo -e "${GREEN}✅ Permissions configured${NC}"

echo ""
echo -e "${GREEN}✨ Setup complete!${NC}"
echo ""
echo -e "${BLUE}📋 What's been configured:${NC}"
echo "  • Git hooks for commit message validation"
echo "  • Commit message template (US-<number>: format)"
echo "  • Helper script: ./scripts/new-feature.sh"
echo ""
echo -e "${BLUE}🚀 Quick start:${NC}"
echo "  1. Run: ${YELLOW}./scripts/new-feature.sh${NC} to create a new feature branch"
echo "  2. Make changes"
echo "  3. Commit: ${YELLOW}git commit${NC} (template will auto-populate)"
echo "  4. Your commit message will be automatically validated"
echo ""
echo -e "${BLUE}📚 Learn more:${NC}"
echo "  • Read CONTRIBUTING.md for complete workflow"
echo "  • Check .githooks/ for hook implementations"
echo ""
