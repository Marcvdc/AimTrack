// Shared bits: AimTrack logo, mock data, icons, shooting-specific viz.
// Exposes everything on window so each variation file can pick what it needs.

// ─── Logo ────────────────────────────────────────────────────────────────
function AimTrackLogo({ size = 28, color = 'currentColor', style = {} }) {
  // Wrap in a colored mask so we can swap colors freely. The source SVG
  // ships as solid black paths, so we use it as a CSS mask instead of <img>.
  return (
    <span
      aria-label="AimTrack"
      style={{
        display: 'inline-block',
        width: size,
        height: size,
        background: color,
        WebkitMask: 'url(assets/aimtrack-logo.svg) center/contain no-repeat',
        mask: 'url(assets/aimtrack-logo.svg) center/contain no-repeat',
        flex: '0 0 auto',
        ...style,
      }}
    />
  );
}

function Wordmark({ color = 'currentColor', size = 28, style = {}, accent }) {
  // Logo + AimTrack wordmark inline.
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: size * 0.32, ...style }}>
      <AimTrackLogo size={size} color={accent || color} />
      <span style={{
        fontFamily: 'var(--at-display, "Inter", system-ui, sans-serif)',
        fontWeight: 700,
        fontSize: size * 0.85,
        letterSpacing: '-0.02em',
        color,
        lineHeight: 1,
      }}>
        Aim<span style={{ color: accent || 'inherit' }}>Track</span>
      </span>
    </span>
  );
}

// ─── Mock data: a believable recreational sport-shooter's recent activity ──
const SESSIONS = [
  {
    id: 'S-0247', date: '08 mei',   day: 'wo', range: 'SV Diemen',           discipline: 'Luchtpistool 10m',  weapon: 'Walther LP500',     shots: 60, score: 547, best: 9.7, group: 22, status: 'reflected', ai: true,  note: 'Sterke openingsserie, stabiele pols. Lichte dip rond schot 35-40.' },
    { id: 'S-0246', date: '04 mei',   day: 'za', range: 'SV Diemen',         discipline: 'Pistool 25m',       weapon: 'CZ Shadow 2',       shots: 30, score: 268, best: 10,  group: 41, status: 'pending',   ai: false, note: 'Eerste sessie met nieuwe richtkijker; nog kalibreren.' },
    { id: 'S-0245', date: '29 apr',   day: 'di', range: 'Schuttersgilde Hilversum', discipline: 'Luchtpistool 10m', weapon: 'Walther LP500', shots: 60, score: 552, best: 10,  group: 19, status: 'reflected', ai: true,  note: 'Beste 60 van het jaar. Ademhaling op punt.' },
    { id: 'S-0244', date: '24 apr',   day: 'do', range: 'SV Diemen',         discipline: 'Vrij pistool 50m',  weapon: 'Pardini K22',       shots: 60, score: 515, best: 10,  group: 58, status: 'reflected', ai: true,  note: 'Wind op de baan, concentratie wisselend.' },
    { id: 'S-0243', date: '20 apr',   day: 'zo', range: 'SV Diemen',         discipline: 'Luchtpistool 10m',  weapon: 'Walther LP500',     shots: 40, score: 364, best: 9.9, group: 24, status: 'reflected', ai: true,  note: 'Korte training, focus op standwerk.' },
];

const WEAPONS = [
  { id: 'W-001', name: 'Walther LP500',   type: 'Luchtpistool',   caliber: '4.5 mm',    serial: 'LP500-4421', sessions: 38, shots: 2140, last: '08 mei', avg: 547.3, trend: +2.1 },
  { id: 'W-002', name: 'CZ Shadow 2',     type: 'Pistool',        caliber: '9×19 mm',   serial: 'CZ-A9388B',  sessions: 12, shots: 360,  last: '04 mei', avg: 268.4, trend: -1.2 },
  { id: 'W-003', name: 'Pardini K22',     type: 'Vrij pistool',   caliber: '.22 LR',    serial: 'PK22-7791',  sessions: 9,  shots: 420,  last: '24 apr', avg: 510.0, trend: +3.4 },
];

const TREND_30D = [524, 531, 522, 540, 535, 542, 538, 547, 545, 552, 549, 547, 543, 550, 552, 547];
const RING_HITS = [
  // x,y in [-1..1] within target, ring (1=10, 10=miss)
  { x:  0.05, y: -0.06, r: 10 }, { x: -0.10, y:  0.04, r: 9.7 },
  { x: -0.02, y:  0.12, r: 9.5 }, { x:  0.18, y: -0.04, r: 9.0 },
  { x:  0.08, y:  0.02, r: 9.9 }, { x: -0.06, y: -0.14, r: 9.3 },
  { x:  0.22, y:  0.10, r: 8.6 }, { x: -0.18, y:  0.06, r: 8.9 },
  { x:  0.04, y: -0.08, r: 9.8 }, { x:  0.12, y:  0.14, r: 9.1 },
];

// ─── Target ring viz ──────────────────────────────────────────────────────
function TargetRings({ size = 200, hits = RING_HITS, accent = '#64f4b3', dim = '#ffffff', bg = 'transparent', showHits = true, ringStroke = 1, scoreLabels = false }) {
  const rings = [10, 9, 8, 7, 6, 5, 4, 3];
  const cx = size / 2, cy = size / 2;
  const maxR = size * 0.46;
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ background: bg, display: 'block' }}>
      {rings.map((n, i) => {
        const r = maxR * ((rings.length - i) / rings.length);
        const fill = n <= 4 ? dim : 'transparent';
        const op = n <= 4 ? 0.06 : 0;
        return (
          <circle key={n} cx={cx} cy={cy} r={r}
            fill={fill} fillOpacity={op}
            stroke={dim} strokeOpacity={n === 10 ? 0.55 : 0.22}
            strokeWidth={ringStroke} />
        );
      })}
      {/* center 10-ring dot */}
      <circle cx={cx} cy={cy} r={maxR / rings.length * 0.5} fill={dim} fillOpacity={0.18} />
      {/* crosshair */}
      <line x1={cx - maxR * 1.05} y1={cy} x2={cx + maxR * 1.05} y2={cy} stroke={dim} strokeOpacity={0.18} strokeWidth={ringStroke} strokeDasharray="2 4" />
      <line x1={cx} y1={cy - maxR * 1.05} x2={cx} y2={cy + maxR * 1.05} stroke={dim} strokeOpacity={0.18} strokeWidth={ringStroke} strokeDasharray="2 4" />

      {scoreLabels && rings.filter(n => n >= 7).map((n, i) => {
        const r = maxR * ((rings.length - i) / rings.length);
        return (
          <text key={`l${n}`} x={cx + 4} y={cy - r + 10}
            fill={dim} fillOpacity={0.45}
            style={{ font: '9px ui-monospace, monospace' }}>{n}</text>
        );
      })}

      {showHits && hits.map((h, i) => {
        const x = cx + h.x * maxR * 0.35;
        const y = cy + h.y * maxR * 0.35;
        const isTen = h.r >= 9.5;
        return (
          <g key={i}>
            <circle cx={x} cy={y} r={3.2} fill={accent} fillOpacity={isTen ? 0.95 : 0.55} stroke={accent} strokeWidth={isTen ? 0 : 0.5} />
            {isTen && <circle cx={x} cy={y} r={6} fill="none" stroke={accent} strokeOpacity={0.35} strokeWidth={1} />}
          </g>
        );
      })}
    </svg>
  );
}

// ─── Sparkline ────────────────────────────────────────────────────────────
function Spark({ data = TREND_30D, w = 140, h = 36, color = '#64f4b3', fill = true, strokeW = 1.5 }) {
  const min = Math.min(...data), max = Math.max(...data);
  const span = Math.max(1, max - min);
  const pts = data.map((v, i) => {
    const x = (i / (data.length - 1)) * w;
    const y = h - ((v - min) / span) * (h - 4) - 2;
    return [x, y];
  });
  const d = pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(' ');
  const area = `${d} L${w} ${h} L0 ${h} Z`;
  return (
    <svg width={w} height={h} viewBox={`0 0 ${w} ${h}`} style={{ display: 'block' }}>
      {fill && (
        <>
          <defs>
            <linearGradient id={`sg-${color.replace('#','')}`} x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity="0.35" />
              <stop offset="100%" stopColor={color} stopOpacity="0" />
            </linearGradient>
          </defs>
          <path d={area} fill={`url(#sg-${color.replace('#','')})`} />
        </>
      )}
      <path d={d} fill="none" stroke={color} strokeWidth={strokeW} strokeLinecap="round" strokeLinejoin="round" />
      <circle cx={pts[pts.length-1][0]} cy={pts[pts.length-1][1]} r={2.5} fill={color} />
    </svg>
  );
}

// ─── Tiny icons (no emoji, all stroke-based for tactical feel) ───────────
const Icon = ({ d, size = 16, stroke = 'currentColor', sw = 1.6, fill = 'none', style }) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill={fill} stroke={stroke} strokeWidth={sw} strokeLinecap="round" strokeLinejoin="round" style={style}>
    {typeof d === 'string' ? <path d={d} /> : d}
  </svg>
);

const ICONS = {
  target: <><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2" fill="currentColor"/><line x1="12" y1="1" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="1" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="23" y2="12"/></>,
  crosshair: <><circle cx="12" cy="12" r="9"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="3" y1="12" x2="21" y2="12"/></>,
  weapon: <><path d="M3 14h13l3-3h2v6h-3l-2 2h-3l-1 2H7l-1-2H3z"/><path d="M9 14V9h4v5"/></>,
  session: <><rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/></>,
  ai: <><path d="M12 3l2 4 4 1-3 3 1 4-4-2-4 2 1-4-3-3 4-1z"/></>,
  spark: <><polyline points="3 17 9 11 13 14 21 6"/><polyline points="14 6 21 6 21 13"/></>,
  export: <><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></>,
  search: <><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.5" y2="16.5"/></>,
  bell: <><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></>,
  add: <><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></>,
  arrow: <><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></>,
  up: <><polyline points="6 15 12 9 18 15"/></>,
  down: <><polyline points="6 9 12 15 18 9"/></>,
  filter: <><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></>,
  cal: <><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></>,
  shield: <><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></>,
  chat: <><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></>,
  dot: <><circle cx="12" cy="12" r="3" fill="currentColor"/></>,
  check: <><polyline points="20 6 9 17 4 12"/></>,
  more: <><circle cx="5" cy="12" r="1.4" fill="currentColor"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/><circle cx="19" cy="12" r="1.4" fill="currentColor"/></>,
};

// Expose on window so component scripts in other Babel blocks can use them.
Object.assign(window, {
  AimTrackLogo, Wordmark,
  SESSIONS, WEAPONS, TREND_30D, RING_HITS,
  TargetRings, Spark,
  Icon, ICONS,
});
