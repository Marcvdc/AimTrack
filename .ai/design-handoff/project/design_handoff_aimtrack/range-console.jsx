// Variation 1: "Range Console" — dense Filament-style admin
// Dark, tactical, monospace numbers, target rings inline, AI panel as ticker.

function RangeConsole({ palette, fonts }) {
  const c = palette;
  const { SESSIONS, WEAPONS, TargetRings, Spark, Wordmark, Icon, ICONS, Reticle } = window;

  const styles = {
    root: {
      width: '100%', height: '100%', display: 'flex',
      background: c.bg, color: c.text,
      fontFamily: fonts.body,
      fontSize: 13, lineHeight: 1.4,
      letterSpacing: '0.005em',
    },
    side: {
      width: 220, flex: '0 0 220px',
      borderRight: `1px solid ${c.line}`,
      background: c.panel,
      display: 'flex', flexDirection: 'column',
    },
    sideHead: { padding: '18px 18px 22px', borderBottom: `1px solid ${c.line}` },
    sideNav: { flex: 1, padding: '14px 8px', display: 'flex', flexDirection: 'column', gap: 1 },
    navGroup: { padding: '14px 12px 4px', fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted, fontFamily: fonts.mono },
    navItem: (active) => ({
      display: 'flex', alignItems: 'center', gap: 10,
      padding: '8px 12px', borderRadius: 6,
      color: active ? c.text : c.muted,
      background: active ? c.panel2 : 'transparent',
      borderLeft: `2px solid ${active ? c.accent : 'transparent'}`,
      cursor: 'pointer', fontWeight: active ? 600 : 500,
    }),
    sideFoot: { padding: '14px 18px', borderTop: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 10 },
    avatar: { width: 32, height: 32, borderRadius: 6, background: c.panel2, display: 'flex', alignItems: 'center', justifyContent: 'center', color: c.accent, fontFamily: fonts.mono, fontWeight: 700, fontSize: 13 },

    main: { flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 },
    topbar: {
      height: 56, padding: '0 24px',
      display: 'flex', alignItems: 'center', gap: 16,
      borderBottom: `1px solid ${c.line}`, background: c.bg,
    },
    crumb: { fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.08em' },
    crumbActive: { color: c.text },
    search: { flex: 1, maxWidth: 380, marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8, padding: '6px 10px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 6, color: c.muted, fontFamily: fonts.mono, fontSize: 11 },
    btn: { padding: '7px 12px', borderRadius: 6, border: `1px solid ${c.line}`, background: c.panel, color: c.text, display: 'flex', alignItems: 'center', gap: 6, fontFamily: fonts.body, fontSize: 12, cursor: 'pointer' },
    btnPrim: { padding: '7px 12px', borderRadius: 6, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 6, fontFamily: fonts.body, fontSize: 12, cursor: 'pointer' },

    body: { flex: 1, padding: 24, display: 'grid', gridTemplateColumns: '1fr 320px', gap: 16, overflow: 'hidden' },
    col: { display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 },

    h1: { fontSize: 22, fontWeight: 600, letterSpacing: '-0.01em', margin: 0, color: c.text },
    sub: { color: c.muted, fontSize: 12, margin: '4px 0 0' },

    statsRow: { display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 },
    stat: { padding: 16, background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8 },
    statLabel: { fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted },
    statValue: { fontFamily: fonts.mono, fontSize: 26, fontWeight: 600, marginTop: 6, color: c.text, letterSpacing: '-0.01em' },
    statSub: { fontSize: 11, color: c.muted, marginTop: 4, display: 'flex', alignItems: 'center', gap: 4 },

    card: { background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8, overflow: 'hidden' },
    cardHead: { padding: '14px 16px', borderBottom: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 10 },
    cardTitle: { fontSize: 13, fontWeight: 600, color: c.text, margin: 0 },
    cardSub: { fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.12em', textTransform: 'uppercase', color: c.muted, marginLeft: 'auto' },

    tableHead: { display: 'grid', gridTemplateColumns: '70px 1fr 130px 90px 70px 90px 60px', padding: '8px 16px', fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.12em', textTransform: 'uppercase', color: c.muted, borderBottom: `1px solid ${c.line}` },
    tableRow: { display: 'grid', gridTemplateColumns: '70px 1fr 130px 90px 70px 90px 60px', padding: '12px 16px', borderBottom: `1px solid ${c.line}`, alignItems: 'center', fontSize: 12 },
    rowDate: { fontFamily: fonts.mono, color: c.muted, fontSize: 11 },
    rowMain: { display: 'flex', flexDirection: 'column', gap: 2 },
    rowDisc: { fontWeight: 600, color: c.text },
    rowMeta: { fontSize: 11, color: c.muted },
    rowMono: { fontFamily: fonts.mono, color: c.text },
    badge: (kind) => ({
      display: 'inline-flex', alignItems: 'center', gap: 5,
      padding: '3px 7px', borderRadius: 4,
      fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.08em', textTransform: 'uppercase',
      background: kind === 'ok' ? `${c.accent}1f` : kind === 'pending' ? `${c.warn}1a` : `${c.muted}14`,
      color: kind === 'ok' ? c.accent : kind === 'pending' ? c.warn : c.muted,
      border: `1px solid ${kind === 'ok' ? c.accent + '33' : kind === 'pending' ? c.warn + '33' : c.line}`,
    }),

    aiCard: { background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8, padding: 16, display: 'flex', flexDirection: 'column', gap: 12 },
    aiHead: { display: 'flex', alignItems: 'center', gap: 8, fontSize: 11, fontFamily: fonts.mono, letterSpacing: '0.12em', textTransform: 'uppercase', color: c.accent },
    aiQuote: { fontSize: 13, lineHeight: 1.55, color: c.text, fontFamily: fonts.body },
    aiKv: { display: 'grid', gridTemplateColumns: '90px 1fr', gap: 4, fontSize: 11 },
    aiK: { fontFamily: fonts.mono, color: c.muted, letterSpacing: '0.08em', textTransform: 'uppercase' },
    aiV: { color: c.text },
  };

  const NAV = [
    { group: 'log' },
    { icon: ICONS.session, label: 'Sessies', active: true, badge: '247' },
    { icon: ICONS.weapon,  label: 'Wapens', badge: '3' },
    { icon: ICONS.cal,     label: 'Kalender' },
    { group: 'inzicht' },
    { icon: ICONS.ai,      label: 'AI-coach', badge: 'NEW' },
    { icon: ICONS.spark,   label: 'Trends' },
    { icon: ICONS.target,  label: 'Wapen-inzicht' },
    { group: 'beheer' },
    { icon: ICONS.export,  label: 'Export · WM-4' },
    { icon: ICONS.shield,  label: 'Privacy' },
  ];

  return (
    <div style={styles.root}>
      {/* Sidebar */}
      <aside style={styles.side}>
        <div style={styles.sideHead}>
          <Wordmark size={26} color={c.text} accent={c.accent} />
          <div style={{ marginTop: 10, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>
            v3.2 · self-hosted
          </div>
        </div>
        <nav style={styles.sideNav}>
          {NAV.map((n, i) => n.group ? (
            <div key={i} style={styles.navGroup}>{n.group}</div>
          ) : (
            <div key={i} style={styles.navItem(n.active)}>
              <Icon d={n.icon} size={16} sw={1.7} />
              <span style={{ flex: 1 }}>{n.label}</span>
              {n.badge && (
                <span style={{ fontFamily: fonts.mono, fontSize: 10, color: n.badge === 'NEW' ? c.accent : c.muted, letterSpacing: '0.08em' }}>{n.badge}</span>
              )}
            </div>
          ))}
        </nav>
        <div style={styles.sideFoot}>
          <div style={styles.avatar}>MV</div>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontSize: 12, color: c.text, fontWeight: 600 }}>Marc v.d.C.</div>
            <div style={{ fontSize: 11, color: c.muted, fontFamily: fonts.mono }}>SV Diemen · B-licentie</div>
          </div>
        </div>
      </aside>

      {/* Main */}
      <div style={styles.main}>
        {/* Topbar */}
        <div style={styles.topbar}>
          <div style={styles.crumb}>LOG / <span style={styles.crumbActive}>SESSIES</span></div>
          <div style={styles.search}>
            <Icon d={ICONS.search} size={13} stroke={c.muted} />
            <span>Zoek sessie, wapen, baan…</span>
            <span style={{ marginLeft: 'auto', padding: '1px 5px', border: `1px solid ${c.line}`, borderRadius: 3, color: c.muted, fontSize: 10 }}>⌘K</span>
          </div>
          <button style={styles.btn}><Icon d={ICONS.filter} size={13} />Filter</button>
          <button style={styles.btn}><Icon d={ICONS.export} size={13} />Export</button>
          <button style={styles.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} />Nieuwe sessie</button>
        </div>

        <div style={styles.body}>
          <div style={styles.col}>
            {/* Page header with reticle watermark (T1) */}
            <div style={{ position: 'relative', overflow: 'visible' }}>
              <window.WatermarkBg palette={c} size={180} top={-30} right={-20} opacity={0.08} />
              <div style={{ position: 'relative', zIndex: 1 }}>
                <h1 style={styles.h1}>Sessies</h1>
                <p style={styles.sub}>Overzicht van je trainingen · {SESSIONS.length} recent · gefilterd op afgelopen 30 dagen</p>
              </div>
            </div>

            {/* Stats */}
            <div style={styles.statsRow}>
              <div style={styles.stat}>
                <div style={styles.statLabel}>Sessies / mnd</div>
                <div style={styles.statValue}>14</div>
                <div style={styles.statSub}><Icon d={ICONS.up} size={12} stroke={c.accent} /> <span style={{ color: c.accent }}>+3</span> vs apr</div>
              </div>
              <div style={styles.stat}>
                <div style={styles.statLabel}>Schoten totaal</div>
                <div style={styles.statValue}>2.920</div>
                <div style={styles.statSub}><span style={{ color: c.muted }}>laatste 30d</span></div>
              </div>
              <div style={styles.stat}>
                <div style={styles.statLabel}>Beste serie LP10</div>
                <div style={styles.statValue}>552</div>
                <div style={styles.statSub}><Icon d={ICONS.up} size={12} stroke={c.accent} /> <span style={{ color: c.accent }}>+8</span> persoonlijk record</div>
              </div>
              <div style={styles.stat}>
                <div style={styles.statLabel}>AI-reflecties</div>
                <div style={styles.statValue}>11</div>
                <div style={styles.statSub}><span style={{ color: c.muted }}>3 in wachtrij</span></div>
              </div>
            </div>

            {/* Sessions table */}
            <div style={styles.card}>
              <div style={styles.cardHead}>
                <Icon d={ICONS.session} size={14} stroke={c.accent} />
                <div style={styles.cardTitle}>Recente sessies</div>
                <div style={styles.cardSub}>5 / 247</div>
              </div>
              <div style={styles.tableHead}>
                <div>Datum</div><div>Discipline · Baan</div><div>Wapen</div><div>Schoten</div><div>Score</div><div>Status</div><div></div>
              </div>
              {SESSIONS.map((s) => (
                <div key={s.id} style={styles.tableRow}>
                  <div style={styles.rowDate}>{s.date}<div style={{ fontSize: 9, opacity: 0.7 }}>{s.day} · {s.id}</div></div>
                  <div style={styles.rowMain}>
                    <div style={styles.rowDisc}>{s.discipline}</div>
                    <div style={styles.rowMeta}>{s.range}</div>
                  </div>
                  <div style={{ ...styles.rowMono, fontSize: 12 }}>{s.weapon}</div>
                  <div style={styles.rowMono}>{s.shots}</div>
                  <div style={{ ...styles.rowMono, fontWeight: 600, color: c.accent }}>{s.score}</div>
                  <div>
                    {s.status === 'reflected' && s.ai && <span style={styles.badge('ok')}><Icon d={ICONS.ai} size={10} /> AI</span>}
                    {s.status === 'pending' && <span style={styles.badge('pending')}>•  open</span>}
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                    <Icon d={ICONS.more} size={14} stroke={c.muted} />
                  </div>
                </div>
              ))}
            </div>

            {/* Wapens row */}
            <div style={styles.card}>
              <div style={styles.cardHead}>
                <Icon d={ICONS.weapon} size={14} stroke={c.accent} />
                <div style={styles.cardTitle}>Wapens · gebruik</div>
                <div style={styles.cardSub}>3 actief</div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 0 }}>
                {WEAPONS.map((w, i) => (
                  <div key={w.id} style={{ padding: 16, borderRight: i < 2 ? `1px solid ${c.line}` : 'none', display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em', textTransform: 'uppercase' }}>{w.type} · {w.caliber}</div>
                    <div style={{ fontSize: 14, fontWeight: 600, color: c.text }}>{w.name}</div>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                      <div>
                        <div style={{ fontFamily: fonts.mono, fontSize: 18, color: c.text, fontWeight: 600 }}>{w.avg.toFixed(1)}</div>
                        <div style={{ fontSize: 10, color: c.muted, fontFamily: fonts.mono }}>{w.sessions} sessies · {w.shots} schoten</div>
                      </div>
                      <Spark data={[w.avg-12,w.avg-8,w.avg-5,w.avg-7,w.avg-3,w.avg-1,w.avg+1,w.avg+w.trend]} w={80} h={32} color={w.trend >= 0 ? c.accent : c.warn} />
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>

          {/* Right column: AI panel + last session viz */}
          <div style={styles.col}>
            <div style={styles.card}>
              <div style={styles.cardHead}>
                <Icon d={ICONS.target} size={14} stroke={c.accent} />
                <div style={styles.cardTitle}>Laatste sessie</div>
                <div style={styles.cardSub}>S-0247</div>
              </div>
              <div style={{ padding: 18, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12 }}>
                <TargetRings size={200} accent={c.accent} dim={c.text} />
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 14, width: '100%' }}>
                  <div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.14em', textTransform: 'uppercase', color: c.muted }}>Score</div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 18, fontWeight: 600, color: c.accent }}>547</div>
                  </div>
                  <div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.14em', textTransform: 'uppercase', color: c.muted }}>Beste</div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 18, fontWeight: 600, color: c.text }}>9.7</div>
                  </div>
                  <div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.14em', textTransform: 'uppercase', color: c.muted }}>Groep</div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 18, fontWeight: 600, color: c.text }}>22<span style={{ fontSize: 11, color: c.muted }}>mm</span></div>
                  </div>
                </div>
              </div>
            </div>

            <window.BracketFrame palette={c} bordered panel rounded={8} padding={16} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              <div style={styles.aiHead}>
                <Icon d={ICONS.ai} size={12} stroke={c.accent} />
                AI-reflectie · S-0247
                <span style={{ marginLeft: 'auto', color: c.muted, fontFamily: fonts.mono }}>2 min</span>
              </div>
              <div style={styles.aiQuote}>
                "Sterke openingsserie met 5× tien op rij. Vanaf schot 35 daalt het gemiddelde met 0.4 — typisch concentratie­dipje rond minuut 22 van je sessie."
              </div>
              <div style={{ height: 1, background: c.line }} />
              <div style={styles.aiKv}>
                <div style={styles.aiK}>Sterk</div>
                <div style={styles.aiV}>Openingsritme, polsstabiliteit</div>
                <div style={styles.aiK}>Verbeter</div>
                <div style={styles.aiV}>Pauze rond schot 30, ademritme resetten</div>
                <div style={styles.aiK}>Volgende</div>
                <div style={styles.aiV}>Droog­oefenen 2× 10 min, focus stand­werk</div>
              </div>
              <button style={{ ...styles.btn, justifyContent: 'center', marginTop: 4 }}>
                <Icon d={ICONS.chat} size={13} /> Vraag AI-coach iets
              </button>
            </window.BracketFrame>

            <div style={styles.card}>
              <div style={styles.cardHead}>
                <Icon d={ICONS.spark} size={14} stroke={c.accent} />
                <div style={styles.cardTitle}>Trend · LP500</div>
                <div style={styles.cardSub}>30d</div>
              </div>
              <div style={{ padding: 14 }}>
                <Spark w={280} h={70} color={c.accent} strokeW={1.8} />
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.08em' }}>
                  <span>10 apr</span><span>22 apr</span><span>08 mei</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

window.RangeConsole = RangeConsole;
