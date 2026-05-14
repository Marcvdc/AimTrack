// Shared visual atoms for the "A · Range Console" direction.
// Every A-screen pulls its tokens from here so they read as one system.
//
//   const a = window.aStyles(palette, fonts)
//   <div style={a.card}>…</div>

function aStyles(c, fonts) {
  return {
    // ── base ───────────────────────────────────────────────────────────
    root: {
      width: '100%', height: '100%', display: 'flex',
      background: c.bg, color: c.text,
      fontFamily: fonts.body, fontSize: 13, lineHeight: 1.45,
      letterSpacing: '0.005em',
    },
    panel: { background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8 },

    // ── topbar / sidebar shells ────────────────────────────────────────
    sideShell: { width: 220, flex: '0 0 220px', borderRight: `1px solid ${c.line}`, background: c.panel, display: 'flex', flexDirection: 'column' },
    sideHead:  { padding: '18px 18px 22px', borderBottom: `1px solid ${c.line}` },
    sideNav:   { flex: 1, padding: '14px 8px', display: 'flex', flexDirection: 'column', gap: 1 },
    navGroup:  { padding: '14px 12px 4px', fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted, fontFamily: fonts.mono },
    navItem: (active) => ({
      display: 'flex', alignItems: 'center', gap: 10,
      padding: '8px 12px', borderRadius: 6,
      color: active ? c.text : c.muted,
      background: active ? c.panel2 : 'transparent',
      borderLeft: `2px solid ${active ? c.accent : 'transparent'}`,
      cursor: 'pointer', fontWeight: active ? 600 : 500,
    }),
    sideFoot:  { padding: '14px 18px', borderTop: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 10 },
    avatar:    { width: 32, height: 32, borderRadius: 6, background: c.panel2, display: 'flex', alignItems: 'center', justifyContent: 'center', color: c.accent, fontFamily: fonts.mono, fontWeight: 700, fontSize: 13 },

    topbar:    { height: 56, padding: '0 24px', display: 'flex', alignItems: 'center', gap: 16, borderBottom: `1px solid ${c.line}`, background: c.bg },
    crumb:     { fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.08em' },
    crumbActive:{ color: c.text },
    search:    { flex: 1, maxWidth: 380, marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8, padding: '6px 10px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 6, color: c.muted, fontFamily: fonts.mono, fontSize: 11 },

    btn:       { padding: '7px 12px', borderRadius: 6, border: `1px solid ${c.line}`, background: c.panel, color: c.text, display: 'flex', alignItems: 'center', gap: 6, fontFamily: fonts.body, fontSize: 12, cursor: 'pointer' },
    btnPrim:   { padding: '7px 12px', borderRadius: 6, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 6, fontFamily: fonts.body, fontSize: 12, cursor: 'pointer' },
    btnGhost:  { padding: '7px 12px', borderRadius: 6, border: `1px solid ${c.accent}40`, background: `${c.accent}10`, color: c.accent, display: 'flex', alignItems: 'center', gap: 6, fontFamily: fonts.body, fontSize: 12, cursor: 'pointer' },

    // ── headings & typography ──────────────────────────────────────────
    h1: { fontSize: 22, fontWeight: 600, letterSpacing: '-0.01em', margin: 0, color: c.text },
    h2: { fontSize: 17, fontWeight: 600, letterSpacing: '-0.005em', margin: 0, color: c.text },
    sub:{ color: c.muted, fontSize: 12, margin: '4px 0 0' },
    label: { fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted },
    mono: { fontFamily: fonts.mono, color: c.text },

    // ── card ───────────────────────────────────────────────────────────
    card:      { background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8, overflow: 'hidden' },
    cardHead:  { padding: '14px 16px', borderBottom: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 10 },
    cardTitle: { fontSize: 13, fontWeight: 600, color: c.text, margin: 0 },
    cardSub:   { fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.12em', textTransform: 'uppercase', color: c.muted, marginLeft: 'auto' },

    // ── stat ───────────────────────────────────────────────────────────
    stat:       { padding: 16, background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8 },
    statLabel:  { fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted },
    statValue:  { fontFamily: fonts.mono, fontSize: 26, fontWeight: 600, marginTop: 6, color: c.text, letterSpacing: '-0.01em' },
    statSub:    { fontSize: 11, color: c.muted, marginTop: 4, display: 'flex', alignItems: 'center', gap: 4 },

    // ── badge ──────────────────────────────────────────────────────────
    badge: (kind) => ({
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: '3px 7px', borderRadius: 4,
      fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.08em', textTransform: 'uppercase',
      background: kind === 'ok' ? `${c.accent}1f` : kind === 'pending' ? `${c.warn}1a` : `${c.muted}14`,
      color: kind === 'ok' ? c.accent : kind === 'pending' ? c.warn : c.muted,
      border: `1px solid ${kind === 'ok' ? c.accent + '33' : kind === 'pending' ? c.warn + '33' : c.line}`,
    }),
  };
}

// ── Reusable shell parts ─────────────────────────────────────────────────
function ASidebar({ palette: c, fonts, active }) {
  const a = aStyles(c, fonts);
  const { Wordmark, Icon, ICONS } = window;
  const NAV = [
    { group: 'log' },
    { key: 'sessies',  icon: ICONS.session, label: 'Sessies',      badge: '247' },
    { key: 'wapens',   icon: ICONS.weapon,  label: 'Wapens',       badge: '3' },
    { key: 'kalender', icon: ICONS.cal,     label: 'Kalender' },
    { group: 'inzicht' },
    { key: 'coach',    icon: ICONS.ai,      label: 'AI-coach',     badge: 'NEW' },
    { key: 'trends',   icon: ICONS.spark,   label: 'Trends' },
    { key: 'wapen-inz',icon: ICONS.target,  label: 'Wapen-inzicht' },
    { group: 'beheer' },
    { key: 'export',   icon: ICONS.export,  label: 'Export · WM-4' },
    { key: 'privacy',  icon: ICONS.shield,  label: 'Privacy' },
  ];
  return (
    <aside style={a.sideShell}>
      <div style={a.sideHead}>
        <Wordmark size={26} color={c.text} accent={c.accent} />
        <div style={{ marginTop: 10, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>
          v3.2 · self-hosted
        </div>
      </div>
      <nav style={a.sideNav}>
        {NAV.map((n, i) => n.group ? (
          <div key={i} style={a.navGroup}>{n.group}</div>
        ) : (
          <div key={i} style={a.navItem(active === n.key)}>
            <Icon d={n.icon} size={16} sw={1.7} />
            <span style={{ flex: 1 }}>{n.label}</span>
            {n.badge && (
              <span style={{ fontFamily: fonts.mono, fontSize: 10, color: n.badge === 'NEW' ? c.accent : c.muted, letterSpacing: '0.08em' }}>{n.badge}</span>
            )}
          </div>
        ))}
      </nav>
      <div style={a.sideFoot}>
        <div style={a.avatar}>MV</div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 12, color: c.text, fontWeight: 600 }}>Marc v.d.C.</div>
          <div style={{ fontSize: 11, color: c.muted, fontFamily: fonts.mono }}>SV Diemen · B-licentie</div>
        </div>
      </div>
    </aside>
  );
}

function ATopbar({ palette: c, fonts, crumbs = [], children }) {
  const a = aStyles(c, fonts);
  const { Icon, ICONS } = window;
  return (
    <div style={a.topbar}>
      <div style={a.crumb}>
        {crumbs.map((cr, i) => (
          <React.Fragment key={i}>
            {i > 0 && ' / '}
            <span style={i === crumbs.length - 1 ? a.crumbActive : null}>{cr}</span>
          </React.Fragment>
        ))}
      </div>
      <div style={a.search}>
        <Icon d={ICONS.search} size={13} stroke={c.muted} />
        <span>Zoek sessie, wapen, baan…</span>
        <span style={{ marginLeft: 'auto', padding: '1px 5px', border: `1px solid ${c.line}`, borderRadius: 3, color: c.muted, fontSize: 10 }}>⌘K</span>
      </div>
      {children}
    </div>
  );
}

Object.assign(window, { aStyles, ASidebar, ATopbar });
