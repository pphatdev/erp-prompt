<template>
    <div class="fm-wrap" ref="wrapRoot">
        <svg ref="svgRoot" :width="layout.width" :height="layout.height"
            :viewBox="viewBox"
            xmlns="http://www.w3.org/2000/svg" class="block fm-svg"
            :class="{ 'fm-svg-dragging': !!dragState, 'fm-svg-panning': !!panState }"
            @wheel="onWheel"
            @mousedown.self="onCanvasMousedown">
        <defs>
            <pattern id="fm-grid" :width="24" :height="24" patternUnits="userSpaceOnUse">
                <circle cx="1" cy="1" r="1" class="grid-dot" />
            </pattern>
            <filter id="fm-shadow" x="-10%" y="-10%" width="120%" height="130%">
                <feDropShadow dx="0" dy="3" stdDeviation="4" flood-opacity="0.10" />
            </filter>
            <marker id="ws-arrow" viewBox="0 0 8 8" refX="7" refY="4"
                markerWidth="5" markerHeight="5" orient="auto-start-reverse">
                <path d="M 0 0 L 8 4 L 0 8" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round" />
            </marker>
            <marker id="ws-arrow-active" viewBox="0 0 8 8" refX="7" refY="4"
                markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                <path d="M 0 0 L 8 4 L 0 8" fill="none" class="edge-marker-active"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </marker>
        </defs>

        <rect :width="layout.width" :height="layout.height" fill="url(#fm-grid)" class="grid-bg" />

        <!-- Trigger above the initial node. -->
        <g v-if="initialNode">
            <circle :cx="effectivePos(initialNode).x + NODE_WIDTH / 2"
                :cy="effectivePos(initialNode).y - START_DROP" r="9"
                class="start-puck-outer" />
            <circle :cx="effectivePos(initialNode).x + NODE_WIDTH / 2"
                :cy="effectivePos(initialNode).y - START_DROP" r="4"
                class="start-puck-inner" />
            <path :d="startEdgePath" fill="none"
                :class="['edge', { 'edge-muted': hoverKey && hoverKey !== initialNode.key }]"
                marker-end="url(#ws-arrow)" />
        </g>

        <!-- Edges (recomputed live from effective positions). -->
        <g class="edges">
            <path v-for="edge in liveEdges" :key="`${edge.from}->${edge.to}`"
                :d="edge.path" fill="none"
                :class="[
                    'edge',
                    { 'edge-active': isEdgeActive(edge),
                      'edge-muted': hoverKey && !isEdgeActive(edge) }
                ]"
                :stroke-dasharray="edge.dashed ? '4 4' : undefined"
                :marker-end="isEdgeActive(edge) ? 'url(#ws-arrow-active)' : 'url(#ws-arrow)'" />
        </g>

        <!-- Nodes. -->
        <g class="nodes">
            <g v-for="node in layout.nodes" :key="`n-${node.key}`"
                :transform="`translate(${effectivePos(node).x}, ${effectivePos(node).y})`"
                @mouseenter="onNodeMouseenter(node)"
                @mouseleave="onNodeMouseleave(node)"
                @mousedown.stop="onNodeMousedown(node, $event)"
                :class="['node-group', { 'node-dragging': dragState?.key === node.key }]">
                <rect :width="NODE_WIDTH" :height="NODE_HEIGHT" rx="8" ry="8"
                    filter="url(#fm-shadow)"
                    :class="[
                        'node-card',
                        { 'node-hovered': hoverKey === node.key,
                          'node-faded': hoverKey && hoverKey !== node.key && !isOutgoingTarget(node.key),
                        }
                    ]" />
                <path :d="topAccentPath" :class="['node-accent', `accent-${node.color || 'secondary'}`]" />
                <foreignObject :x="0" :y="ACCENT_HEIGHT" :width="NODE_WIDTH" :height="NODE_HEIGHT - ACCENT_HEIGHT">
                    <div class="node-body">
                        <div class="node-header">
                            <span :class="['node-icon-tile', `tile-${node.color || 'secondary'}`]">
                                <i :class="['ti', node.icon || 'ti-circle-dot', `icon-${node.color || 'secondary'}`]" />
                            </span>
                            <div class="node-header-text">
                                <div class="node-label">{{ node.label }}</div>
                                <div class="node-sub">
                                    <span class="node-key">{{ node.key }}</span>
                                </div>
                            </div>
                            <span :class="['node-type', `type-${node.isInitial ? 'init' : node.isTerminal ? 'end' : 'state'}`]">
                                {{ node.isInitial ? 'INIT' : node.isTerminal ? 'END' : 'STATE' }}
                            </span>
                        </div>
                    </div>
                </foreignObject>
                <!-- Read-only connection dots. Position differs by lane:
                     pipeline has top-input + bottom-output (vertical chain);
                     terminals receive on the LEFT edge. No drag-to-connect. -->
                <template v-if="node.lane === 'offramp'">
                    <circle :cx="0" :cy="NODE_HEIGHT / 2" :r="DOT_RADIUS"
                        :class="['conn-dot', `dot-${node.color || 'secondary'}`,
                                 { 'dot-target': isOutgoingTarget(node.key) }]" />
                </template>
                <template v-else>
                    <circle :cx="NODE_WIDTH / 2" :cy="0" :r="DOT_RADIUS"
                        :class="['conn-dot', `dot-${node.color || 'secondary'}`,
                                 { 'dot-target': isOutgoingTarget(node.key) }]" />
                    <circle v-if="node.allowedNext.length > 0"
                        :cx="NODE_WIDTH / 2" :cy="NODE_HEIGHT" :r="DOT_RADIUS"
                        :class="['conn-dot', `dot-${node.color || 'secondary'}`,
                                 { 'dot-active': hoverKey === node.key }]" />
                </template>
            </g>
        </g>
        </svg>

        <!-- Floating zoom + reset controls — bottom-right of canvas. -->
        <div class="fm-controls" @mousedown.stop>
            <button type="button" class="fm-ctrl" title="Zoom in" aria-label="Zoom in"
                @click="zoomBy(1.2)">
                <i class="ti ti-plus" />
            </button>
            <button type="button" class="fm-ctrl" title="Zoom out" aria-label="Zoom out"
                @click="zoomBy(1 / 1.2)">
                <i class="ti ti-minus" />
            </button>
            <button type="button" class="fm-ctrl" title="Fit to view" aria-label="Fit to view"
                @click="fitToView">
                <i class="ti ti-arrows-diagonal-minimize-2" />
            </button>
            <button type="button" class="fm-ctrl" title="Reset zoom + pan"
                aria-label="Reset zoom and pan" @click="resetView">
                <i class="ti ti-focus-2" />
            </button>
            <span class="fm-ctrl-level" :title="`Zoom level: ${Math.round(zoom * 100)}%`">
                {{ Math.round(zoom * 100) }}%
            </span>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue'

export interface FlowNode {
    key: string
    label: string
    color: string | null
    icon: string | null
    isInitial: boolean
    isTerminal: boolean
    x: number
    y: number
    lane: 'pipeline' | 'offramp'
    allowedNext: string[]
}

export interface FlowEdge {
    from: string
    to: string
    path: string
    dashed: boolean
}

export interface FlowLayout {
    nodes: FlowNode[]
    edges: FlowEdge[]
    width: number
    height: number
}

const props = defineProps<{
    layout: FlowLayout
    hoverKey: string | null
}>()

const emit = defineEmits<{
    (e: 'hover', key: string | null): void
}>()

// Geometry — locked to the parent's layout engine.
const NODE_WIDTH = 220
const NODE_HEIGHT = 84
const ACCENT_HEIGHT = 5
const DOT_RADIUS = 5
const START_DROP = 28
// Threshold above which a click counts as a drag (in SVG units). Below
// this, releasing the mouse counts as a hover/click and positions don't
// change — prevents accidental nudges.
const DRAG_THRESHOLD = 3

const svgRoot = ref<SVGSVGElement | null>(null)
const wrapRoot = ref<HTMLDivElement | null>(null)

// Ephemeral position overrides keyed by node.key. Resets on page reload
// or when the parent re-issues a new layout. Per-user, per-session.
const positions = reactive<Record<string, { x: number; y: number }>>({})

// --------------------------------------------------------------
// Zoom + pan
// --------------------------------------------------------------

const ZOOM_MIN = 0.25
const ZOOM_MAX = 4
const zoom = ref(1)
const pan = reactive({ x: 0, y: 0 })

const viewBox = computed(() => {
    const w = props.layout.width / zoom.value
    const h = props.layout.height / zoom.value
    return `${pan.x} ${pan.y} ${w} ${h}`
})

const clampZoom = (z: number) => Math.max(ZOOM_MIN, Math.min(ZOOM_MAX, z))

/**
 * Zoom centered on the cursor (or canvas center when no cursor is
 * supplied). Adjusts `pan` so the SVG-userspace point under the cursor
 * stays in the same screen position after the zoom changes.
 */
const zoomBy = (factor: number, cursor?: { x: number; y: number }) => {
    const oldZoom = zoom.value
    const newZoom = clampZoom(oldZoom * factor)
    if (newZoom === oldZoom) return
    const focal = cursor ?? {
        x: pan.x + props.layout.width / oldZoom / 2,
        y: pan.y + props.layout.height / oldZoom / 2,
    }
    // Pan adjustment so the focal point lands at the same SVG-userspace
    // location after the zoom: pan += focal * (1/oldZoom - 1/newZoom).
    pan.x = focal.x - (focal.x - pan.x) * (oldZoom / newZoom)
    pan.y = focal.y - (focal.y - pan.y) * (oldZoom / newZoom)
    zoom.value = newZoom
}

const resetView = () => {
    zoom.value = 1
    pan.x = 0
    pan.y = 0
}

/**
 * Compute the zoom level needed to fit the full diagram inside the
 * visible container. Picks the smaller of the width/height ratios
 * so the entire layout is visible without scrolling.
 */
const fitToView = () => {
    const wrap = wrapRoot.value
    if (!wrap) {
        resetView()
        return
    }
    const rect = wrap.getBoundingClientRect()
    if (rect.width <= 0 || rect.height <= 0) {
        resetView()
        return
    }
    const fitW = rect.width / props.layout.width
    const fitH = rect.height / props.layout.height
    zoom.value = clampZoom(Math.min(fitW, fitH) * 0.95)
    pan.x = 0
    pan.y = 0
}

/**
 * Ctrl/Cmd + wheel zooms around the cursor. Plain wheel scrolls the
 * page normally — we never preventDefault unless the modifier is held
 * so users can scroll past the diagram on long pages.
 */
const onWheel = (ev: WheelEvent) => {
    if (!(ev.ctrlKey || ev.metaKey)) return
    ev.preventDefault()
    const cursor = screenToSvg(ev.clientX, ev.clientY)
    const factor = ev.deltaY < 0 ? 1.12 : 1 / 1.12
    zoomBy(factor, cursor)
}

// Background pan: mousedown on the SVG (NOT on a node) starts pan.
interface PanState {
    startPanX: number
    startPanY: number
    mouseStartX: number
    mouseStartY: number
}
const panState = ref<PanState | null>(null)

const onCanvasMousedown = (ev: MouseEvent) => {
    if (ev.button !== 0) return
    const m = screenToSvg(ev.clientX, ev.clientY)
    panState.value = {
        startPanX: pan.x,
        startPanY: pan.y,
        mouseStartX: m.x,
        mouseStartY: m.y,
    }
    if (typeof document !== 'undefined') {
        document.addEventListener('mousemove', onPanMove)
        document.addEventListener('mouseup', onPanEnd)
    }
}

const onPanMove = (ev: MouseEvent) => {
    const p = panState.value
    if (!p) return
    // Use screenToSvg so panning works correctly at any zoom level.
    const m = screenToSvg(ev.clientX, ev.clientY)
    // Mouse moves RIGHT  -> we want viewport to shift LEFT -> pan.x decreases.
    pan.x = p.startPanX - (m.x - p.mouseStartX)
    pan.y = p.startPanY - (m.y - p.mouseStartY)
}

const onPanEnd = () => {
    panState.value = null
    if (typeof document !== 'undefined') {
        document.removeEventListener('mousemove', onPanMove)
        document.removeEventListener('mouseup', onPanEnd)
    }
}

const effectivePos = (n: FlowNode) => positions[n.key] ?? { x: n.x, y: n.y }

const initialNode = computed<FlowNode | null>(
    () => props.layout.nodes.find(n => n.isInitial) ?? null
)

const startEdgePath = computed(() => {
    const n = initialNode.value
    if (!n) return ''
    const pos = effectivePos(n)
    const cx = pos.x + NODE_WIDTH / 2
    const startY = pos.y - START_DROP + 9
    return `M ${cx} ${startY} V ${pos.y}`
})

const topAccentPath = computed(() => {
    const w = NODE_WIDTH
    const h = ACCENT_HEIGHT
    const r = 8
    return `M ${r} 0 Q 0 0, 0 ${r} V ${h} H ${w} V ${r} Q ${w} 0, ${w - r} 0 Z`
})

// ----------------------------------------------------------------
// Live edge re-routing — paths are rebuilt from the CURRENT positions
// on every render so edges follow nodes as they're dragged.
// ----------------------------------------------------------------

interface Anchor { x: number; y: number }

const buildEdgePath = (src: FlowNode, dst: FlowNode, arrivalY: number | null): string => {
    const sPos = effectivePos(src)
    const dPos = effectivePos(dst)
    const r = 8

    // Cross-lane (pipeline -> terminal): exit RIGHT, arrive LEFT at staggered y.
    if (src.lane !== dst.lane && arrivalY !== null) {
        const sx = sPos.x + NODE_WIDTH
        const sy = sPos.y + NODE_HEIGHT / 2
        const ex = dPos.x
        const ey = arrivalY + (dPos.y - dst.y) // offset by manual drag delta
        if (Math.abs(sy - ey) < 1) {
            return `M ${sx} ${sy} H ${ex}`
        }
        const midX = sx + (ex - sx) / 2
        const dy = ey > sy ? 1 : -1
        return [
            `M ${sx} ${sy}`,
            `H ${midX - r}`,
            `Q ${midX} ${sy}, ${midX} ${sy + r * dy}`,
            `V ${ey - r * dy}`,
            `Q ${midX} ${ey}, ${midX + r} ${ey}`,
            `H ${ex}`,
        ].join(' ')
    }

    // Same-lane: detect skip vs adjacent.
    const sx = sPos.x + NODE_WIDTH / 2
    const sy = sPos.y + NODE_HEIGHT
    const ex = dPos.x + NODE_WIDTH / 2
    const ey = dPos.y
    const verticalDistance = ey - sy
    const sameColumn = Math.abs(sPos.x - dPos.x) < 1

    if (sameColumn && verticalDistance > 0 && verticalDistance < 140) {
        // Adjacent or short hop — straight V.
        return `M ${sx} ${sy} V ${ey}`
    }
    if (sameColumn && verticalDistance >= 140) {
        // Skip edge — Bezier bulge to the right.
        const bulge = Math.min(180, 60 + verticalDistance * 0.18)
        return `M ${sx} ${sy} C ${sx + bulge} ${sy + 30}, ${ex + bulge} ${ey - 30}, ${ex} ${ey}`
    }
    if (verticalDistance <= 0) {
        // Backward — Bezier wrap to the right.
        const bulge = 140
        return `M ${sx} ${sy} C ${sx + bulge} ${sy + 30}, ${ex + bulge} ${ey - 30}, ${ex} ${ey}`
    }
    // Off-axis forward — generic H-V-H elbow.
    const midY = sy + (ey - sy) / 2
    const dx = ex > sx ? 1 : -1
    return [
        `M ${sx} ${sy}`,
        `V ${midY - r}`,
        `Q ${sx} ${midY}, ${sx + r * dx} ${midY}`,
        `H ${ex - r * dx}`,
        `Q ${ex} ${midY}, ${ex} ${midY + r}`,
        `V ${ey}`,
    ].join(' ')
}

/**
 * Compute the arrival y on a terminal's left edge for each cross-lane
 * edge entering it. Distributed evenly across the terminal's CURRENT
 * vertical span (honoring drag offsets), sorted by source y so the
 * visual order stays top-to-bottom.
 */
const arrivalYByEdge = computed<Map<string, number>>(() => {
    const map = new Map<string, number>()
    const byTarget = new Map<string, { src: FlowNode; dst: FlowNode }[]>()
    for (const e of props.layout.edges) {
        const src = props.layout.nodes.find(n => n.key === e.from)
        const dst = props.layout.nodes.find(n => n.key === e.to)
        if (!src || !dst || src.lane === dst.lane) continue
        const list = byTarget.get(e.to) ?? []
        list.push({ src, dst })
        byTarget.set(e.to, list)
    }
    for (const [, list] of byTarget) {
        const sorted = [...list].sort((a, b) => effectivePos(a.src).y - effectivePos(b.src).y)
        const dst = sorted[0].dst
        const dPos = effectivePos(dst)
        const yTop = dPos.y + 12
        const yBottom = dPos.y + NODE_HEIGHT - 12
        sorted.forEach((edge, i) => {
            const slot = sorted.length === 1
                ? dPos.y + NODE_HEIGHT / 2
                : yTop + (i / (sorted.length - 1)) * (yBottom - yTop)
            map.set(`${edge.src.key}->${edge.dst.key}`, slot)
        })
    }
    return map
})

const liveEdges = computed<FlowEdge[]>(() => {
    return props.layout.edges.map(e => {
        const src = props.layout.nodes.find(n => n.key === e.from)
        const dst = props.layout.nodes.find(n => n.key === e.to)
        if (!src || !dst) return e
        const arrival = src.lane !== dst.lane
            ? arrivalYByEdge.value.get(`${e.from}->${e.to}`) ?? null
            : null
        return {
            from: e.from,
            to: e.to,
            dashed: e.dashed,
            path: buildEdgePath(src, dst, arrival),
        }
    })
})

const isEdgeActive = (e: FlowEdge): boolean => props.hoverKey === e.from

const isOutgoingTarget = (key: string): boolean => {
    if (!props.hoverKey) return false
    const src = props.layout.nodes.find(n => n.key === props.hoverKey)
    return !!src && src.allowedNext.includes(key)
}

// ----------------------------------------------------------------
// Node drag (visual repositioning only — no persistence)
// ----------------------------------------------------------------

interface DragState {
    key: string
    nodeStartX: number
    nodeStartY: number
    mouseStartX: number
    mouseStartY: number
    moved: boolean
}

const dragState = ref<DragState | null>(null)

/**
 * Map a client (screen) coordinate into the SVG's userspace, honoring
 * any CSS scaling applied by the parent layout (e.g. `width: 100%`
 * stretches the SVG while the viewBox stays fixed). Without this, drag
 * cursors drift away from the node as the canvas scales.
 */
const screenToSvg = (clientX: number, clientY: number): { x: number; y: number } => {
    const svg = svgRoot.value
    if (!svg) return { x: 0, y: 0 }
    const ctm = svg.getScreenCTM()
    if (!ctm) {
        const rect = svg.getBoundingClientRect()
        return { x: clientX - rect.left, y: clientY - rect.top }
    }
    const pt = svg.createSVGPoint()
    pt.x = clientX
    pt.y = clientY
    const transformed = pt.matrixTransform(ctm.inverse())
    return { x: transformed.x, y: transformed.y }
}

const onNodeMousedown = (node: FlowNode, ev: MouseEvent) => {
    // Left button only.
    if (ev.button !== 0) return
    const m = screenToSvg(ev.clientX, ev.clientY)
    const pos = effectivePos(node)
    dragState.value = {
        key: node.key,
        nodeStartX: pos.x,
        nodeStartY: pos.y,
        mouseStartX: m.x,
        mouseStartY: m.y,
        moved: false,
    }
    if (typeof document !== 'undefined') {
        document.addEventListener('mousemove', onDragMove)
        document.addEventListener('mouseup', onDragEnd)
    }
}

const onDragMove = (ev: MouseEvent) => {
    const d = dragState.value
    if (!d) return
    const m = screenToSvg(ev.clientX, ev.clientY)
    const dx = m.x - d.mouseStartX
    const dy = m.y - d.mouseStartY
    if (!d.moved && Math.hypot(dx, dy) < DRAG_THRESHOLD) return
    d.moved = true
    positions[d.key] = {
        x: Math.max(0, d.nodeStartX + dx),
        y: Math.max(0, d.nodeStartY + dy),
    }
}

const onDragEnd = () => {
    dragState.value = null
    cleanupDragListeners()
}

const cleanupDragListeners = () => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('mousemove', onDragMove)
        document.removeEventListener('mouseup', onDragEnd)
    }
}

const onNodeMouseenter = (node: FlowNode) => {
    if (dragState.value) return  // ignore hover during drag
    emit('hover', node.key)
}

const onNodeMouseleave = (_node: FlowNode) => {
    if (dragState.value) return
    emit('hover', null)
}

onBeforeUnmount(() => {
    cleanupDragListeners()
    if (typeof document !== 'undefined') {
        document.removeEventListener('mousemove', onPanMove)
        document.removeEventListener('mouseup', onPanEnd)
    }
})
</script>

<style scoped>
/* Wrap establishes a relative positioning context so the floating
   zoom controls anchor to the canvas, not the page. */
.fm-wrap {
    position: relative;
    width: 100%;
}

.fm-svg {
    background: color-mix(in srgb, var(--bg-muted) 35%, var(--bg-card));
    border-radius: 0.75rem;
    border: 1px solid var(--border-color);
    cursor: grab;
}
.fm-svg-dragging { cursor: grabbing; }
.fm-svg-panning { cursor: grabbing; }

/* Floating zoom + reset controls — anchored bottom-right of the
   canvas. Stops mousedown so clicking the controls doesn't also
   start a background pan. */
.fm-controls {
    position: absolute;
    right: 12px;
    bottom: 12px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 4px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--bg-card) 92%, transparent);
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.06);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    user-select: none;
}
.fm-ctrl {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--text-body);
    font-size: 14px;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.fm-ctrl:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
}
.fm-ctrl-level {
    padding: 0 8px 0 6px;
    font-family: ui-monospace, 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    min-width: 38px;
    text-align: center;
}

.grid-dot { fill: color-mix(in srgb, var(--text-muted) 30%, transparent); }
.grid-bg { pointer-events: none; }

.start-puck-outer {
    fill: var(--bg-card);
    stroke: var(--color-primary);
    stroke-width: 2;
}
.start-puck-inner { fill: var(--color-primary); }

/* ----- Edges ----- */
.edge {
    stroke: color-mix(in srgb, var(--text-muted) 65%, transparent);
    stroke-width: 1.5;
    color: color-mix(in srgb, var(--text-muted) 65%, transparent);
    fill: none;
    stroke-linejoin: round;
    stroke-linecap: round;
    transition: opacity 0.18s ease, stroke 0.18s ease, stroke-width 0.18s ease;
    pointer-events: none;
}
.edge-active {
    stroke: var(--color-primary);
    stroke-width: 2;
    color: var(--color-primary);
}
.edge-marker-active { stroke: var(--color-primary); }
.edge-muted { opacity: 0.22; }

/* ----- Node card ----- */
.node-group {
    cursor: grab;
    user-select: none;
}
.node-dragging {
    cursor: grabbing;
}
.node-card {
    fill: var(--bg-card);
    stroke: color-mix(in srgb, var(--text-muted) 18%, transparent);
    stroke-width: 1;
    transition: stroke 0.18s ease, stroke-width 0.18s ease, opacity 0.18s ease, filter 0.18s ease;
}
.node-hovered {
    stroke: var(--color-primary);
    stroke-width: 2;
}
.node-dragging .node-card {
    stroke: var(--color-primary);
    stroke-width: 2;
    filter: drop-shadow(0 6px 14px rgb(var(--color-primary-rgb) / 0.25));
}
.node-faded { opacity: 0.42; }

.node-accent { transition: opacity 0.18s ease; }
.node-accent.accent-primary   { fill: rgb(var(--color-primary-rgb)); }
.node-accent.accent-success   { fill: rgb(var(--color-success-rgb)); }
.node-accent.accent-warning   { fill: rgb(var(--color-warning-rgb)); }
.node-accent.accent-danger    { fill: rgb(var(--color-danger-rgb));  }
.node-accent.accent-info      { fill: rgb(var(--color-info-rgb));    }
.node-accent.accent-secondary { fill: color-mix(in srgb, var(--text-muted) 55%, transparent); }

.node-body {
    height: 100%;
    padding: 10px 12px;
    box-sizing: border-box;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.node-header {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    overflow: hidden;
}

.node-icon-tile {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.node-icon-tile.tile-primary   { background: rgb(var(--color-primary-rgb) / 0.12); }
.node-icon-tile.tile-success   { background: rgb(var(--color-success-rgb) / 0.12); }
.node-icon-tile.tile-warning   { background: rgb(var(--color-warning-rgb) / 0.14); }
.node-icon-tile.tile-danger    { background: rgb(var(--color-danger-rgb)  / 0.12); }
.node-icon-tile.tile-info      { background: rgb(var(--color-info-rgb)    / 0.12); }
.node-icon-tile.tile-secondary { background: color-mix(in srgb, var(--text-muted) 12%, transparent); }

.node-icon-tile .ti.icon-primary   { color: rgb(var(--color-primary-rgb)); }
.node-icon-tile .ti.icon-success   { color: rgb(var(--color-success-rgb)); }
.node-icon-tile .ti.icon-warning   { color: rgb(var(--color-warning-rgb)); }
.node-icon-tile .ti.icon-danger    { color: rgb(var(--color-danger-rgb));  }
.node-icon-tile .ti.icon-info      { color: rgb(var(--color-info-rgb));    }
.node-icon-tile .ti.icon-secondary { color: var(--text-muted); }

.node-header-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.node-label {
    font-size: 13.5px;
    font-weight: 600;
    color: var(--text-heading);
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.node-sub {
    display: flex;
    align-items: center;
    gap: 6px;
    line-height: 1.1;
}
.node-key {
    font-family: ui-monospace, 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
    font-size: 10.5px;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.node-type {
    flex-shrink: 0;
    padding: 2px 6px;
    border-radius: 999px;
    font-family: ui-monospace, 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.06em;
    line-height: 1.2;
}
.node-type.type-init {
    background: rgb(var(--color-info-rgb) / 0.18);
    color: rgb(var(--color-info-rgb));
}
.node-type.type-end {
    background: rgb(var(--color-warning-rgb) / 0.20);
    color: rgb(var(--color-warning-rgb));
}
.node-type.type-state {
    background: color-mix(in srgb, var(--text-muted) 14%, transparent);
    color: var(--text-muted);
}

/* ----- Connection dots (read-only indicators) ----- */
.conn-dot {
    fill: var(--bg-card);
    stroke: color-mix(in srgb, var(--text-muted) 55%, transparent);
    stroke-width: 2;
    transition: fill 0.15s ease, stroke 0.15s ease, r 0.15s ease;
    pointer-events: none;
}
.conn-dot.dot-primary   { stroke: rgb(var(--color-primary-rgb) / 0.75); }
.conn-dot.dot-success   { stroke: rgb(var(--color-success-rgb) / 0.75); }
.conn-dot.dot-warning   { stroke: rgb(var(--color-warning-rgb) / 0.75); }
.conn-dot.dot-danger    { stroke: rgb(var(--color-danger-rgb)  / 0.75); }
.conn-dot.dot-info      { stroke: rgb(var(--color-info-rgb)    / 0.75); }
.conn-dot.dot-secondary { stroke: color-mix(in srgb, var(--text-muted) 55%, transparent); }

.conn-dot.dot-active,
.conn-dot.dot-target {
    fill: var(--color-primary);
    stroke: var(--color-primary);
}
</style>
