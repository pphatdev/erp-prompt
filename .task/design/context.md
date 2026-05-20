# Layout & Design System Context

## Overview
A core requirement for our multi-tenant enterprise ERP is a premium UI/UX that feels "alive, responsive, and ultra-high-fidelity" while maintaining absolute performance. The visual specification is defined inside `design.md` and integrated into the global Shell/Layout layout.

## Core Architectural Decisions

### 1. Instantaneous Color Toggling
- **Problem**: When shifting between Light Mode and Dark Mode, browser-based transition variables on borders and backgrounds cause a sluggish "fade" delay.
- **Solution**: Implemented a temporary `.no-transitions` class override on the document body during toggles. This cuts style delays and renders theme updates in <20ms.

### 2. Floating Border Toggle
- **Problem**: In collapsed mode, the inner header padding (`px-5`) and logo width (`w-8`) squeeze standard toggle buttons out of view due to `overflow-hidden`.
- **Solution**: Removed the toggle button from the header container and positioned it absolutely on the sidebar right border (`absolute -right-3 top-5`). This mimics Notion and Linear, ensuring constant accessibility and high visual elegance.

### 3. Customizable Shell Popovers
- Developed unified reactive states (`mega`, `apps`, `notif`, `lang`, `tenant`, `profile`) controlling animated topbar overlays. All popovers include glassmorphic layers and soft shadow elevations.

### 4. Pure CSS Micro-Animations
- Integrated GPU-accelerated keyframe metrics for high-density Task widgets (`orbit` progress loops, `meteor` backdrop highlights, and `ripple` priority trackers) to bypass heavy JS animations.
