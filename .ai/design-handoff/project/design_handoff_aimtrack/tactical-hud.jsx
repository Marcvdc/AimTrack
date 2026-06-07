// Variation 3: "Tactical HUD" — high-contrast scope-readout aesthetic
// Edge-to-edge framed UI mimicking optics: corner brackets, ranging marks,
// reticle-shaped center widget, big monospaced numbers.

function TacticalHUD({ palette, fonts }) {
  const c = palette;
  const { SESSIONS, WEAPONS, TREND_30D, TargetRings, Spark, Wordmark, Icon, ICONS } = window;

  const styles = {
    root: {
      width: '100%', height: '100%',
      background: `radial-gradient(ellipse at center, ${c.panel} 0%, ${c.bg} 90%)`,
      color: c.text, fontFamily: fonts.body,
      position: 'relative',
      display: 'flex', flexDirection: 'column',
      overflow: 'hidden',
    },
    // HUD corner brackets
    corner: (x, y) => ({
      position: 'absolute',
      [x]: 16, [y]: 16,
      width: 28, height: 28,
      borderTop: y === 'top' ? `1.5px solid ${c.accent}` : 'none',
      borderBottom: y === 'bottom' ? `1.5px solid ${c.accent}` : 'none',
      borderLeft: x === 'left' ? `1.5px solid ${c.accent}` : 'none',
      borderRight: x === 'right' ? `1.5px solid ${c.accent}` : 'none',
      pointerEvents: 'none',
    }),

    topbar: {
      height: 48, padding: '0 28px',
      display: 'flex', alignItems: 'center', gap: 28,
      borderBottom: `1px solid ${c.line}`,
      fontFamily: fonts.mono, fontSize: 11, letterSpacing: '0.16em', textTransform: 'uppercase', color: c.muted,
      background: `linear-gradient(to bottom, ${c.panel}, transparent)`,
    },
    tab: (active) => ({
      padding: '0 4px', height: 48, display: 'flex', alignItems: 'center', gap: 8,
      borderBottom: `2px solid ${active ? c.accent : 'transparent'}`,
      color: active ? c.accent : c.muted, cursor: 'pointer',
    }),

    body: { flex: 1, display: 'grid', gridTemplateColumns: '300px 1fr 300px', gap: 0, minHeight: 0 },
    panel: { padding: 24, display: 'flex', flexDirection: 'column', gap: 18, minHeight: 0, overflow: 'hidden' },
    panelDiv: { borderRight: `1px solid ${c.line}` },
    panelDivR: { borderLeft: `1px solid ${c.line}` },

    label: { fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted, display: 'flex', alignItems: 'center', gap: 8 },
    labelDot: { width: 6, height: 6, background: c.accent, borderRadius: 1 },

    bigNum: { fontFamily: fonts.mono, fontSize: 56, fontWeight: 600, lineHeight: 1, color: c.text, letterSpacing: '-0.03em' },
    bigNumAccent: { color: c.accent },
    bigUnit: { fontFamily: fonts.mono, fontSize: 14, color: c.muted, marginLeft: 4, letterSpacing: '0.04em' },

    // Center HUD readout
    centerWrap: { padding: '20px 24px 24px', display: 'flex', flexDirection: 'column', gap: 16, position: 'relative', minHeight: 0 },
    readout: {
      display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 0,
      borderTop: `1px solid ${c.line}`, borderBottom: `1px solid ${c.line}`,
    },
    readoutCell: (i) => ({
      padding: '14px 16px',
      borderRight: i < 3 ? `1px solid ${c.line}` : 'none',
      display: 'flex', flexDirection: 'column', gap: 4,
    }),
    readoutVal: { fontFamily: fonts.mono, fontSize: 22, fontWeight: 600, color: c.text, letterSpacing: '-0.02em' },

    reticleBox: {
      flex: 1,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      position: 'relative',
      background: `radial-gradient(circle at center, ${c.accent}0a 0%, transparent 60%)`,
      minHeight: 0,
    },
    rangeStrip: { display: 'flex', alignItems: 'center', gap: 0, justifyContent: 'space-between', fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.12em', padding: '0 4px' },
    tick: (h) => ({ width: 1, height: h, background: c.muted, opacity: 0.5 }),

    // Side panels
    sessionRow: {
      display: 'grid', gridTemplateColumns: '36px 1fr auto',
      gap: 10, padding: '10px 0',
      borderBottom: `1px solid ${c.line}`,
      alignItems: 'center', fontSize: 12,
    },
    rowDate: { fontFamily: fonts.mono, color: c.accent, fontSize: 12, fontWeight: 600 },
    rowMain: { display: 'flex', flexDirection: 'column', gap: 1, minWidth: 0 },
    rowDisc: { fontWeight: 600, color: c.text, fontSize: 12, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' },
    rowMeta: { fontSize: 10, color: c.muted, fontFamily: fonts.mono, letterSpacing: '0.04em', textTransform: 'uppercase' },
    rowScore: { fontFamily: fonts.mono, color: c.accent, fontWeight: 600, fontSize: 14 },

    statusbar: {
      height: 28, display: 'flex', alignItems: 'center', gap: 0,
      borderTop: `1px solid ${c.line}`,
      background: c.panel,
      fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.16em', textTransform: 'uppercase',
    },
    sbCell: { padding: '0 16px', height: '100%', display: 'flex', alignItems: 'center', gap: 8, borderRight: `1px solid ${c.line}` },
  };

  return (
    <div style={styles.root}>
      {/* HUD corner brackets */}
      <div style={styles.corner('left', 'top')} />
      <div style={styles.corner('right', 'top')} />
      <div style={styles.corner('left', 'bottom')} />
      <div style={styles.corner('right', 'bottom')} />

      {/* Top bar */}
      <div style={styles.topbar}>
        <Wordmark size={22} color={c.text} accent={c.accent} />
        <div style={{ width: 1, height: 22, background: c.line }} />
        <div style={styles.tab(true)}><Icon d={ICONS.session} size={13} /> Range Log</div>
        <div style={styles.tab()}><Icon d={ICONS.weapon} size={13} /> Wapens</div>
        <div style={styles.tab()}><Icon d={ICONS.spark} size={13} /> Trends</div>
        <div style={styles.tab()}><Icon d={ICONS.ai} size={13} /> AI-coach</div>
        <div style={styles.tab()}><Icon d={ICONS.export} size={13} /> Export</div>
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 16 }}>
          <span><span style={{ color: c.accent }}>●</span> SESSIE 247 ACTIEF</span>
          <span>10 MEI · 14:22</span>
          <span>MV-01</span>
        </div>
      </div>

      {/* Body */}
      <div style={styles.body}>
        {/* Left panel — recent log */}
        <div style={{ ...styles.panel, ...styles.panelDiv }}>
          <div>
            <div style={styles.label}><span style={styles.labelDot} /> Recente sessies · 30d</div>
            <div style={{ ...styles.bigNum, marginTop: 8 }}>14<span style={styles.bigUnit}>sessies</span></div>
            <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.accent, marginTop: 4, letterSpacing: '0.04em' }}>+3 vs APR · 2.920 schoten</div>
          </div>

          <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minHeight: 0, overflow: 'hidden' }}>
            <div style={{ ...styles.label, marginBottom: 6 }}>Logboek</div>
            {SESSIONS.map((s, i) => (
              <div key={s.id} style={styles.sessionRow}>
                <div style={styles.rowDate}>{s.date.split(' ')[0]}</div>
                <div style={styles.rowMain}>
                  <div style={styles.rowDisc}>{s.discipline}</div>
                  <div style={styles.rowMeta}>{s.weapon}</div>
                </div>
                <div style={styles.rowScore}>{s.score}</div>
              </div>
            ))}
          </div>

          <button style={{ padding: '10px 14px', borderRadius: 4, border: `1px solid ${c.accent}`, background: 'transparent', color: c.accent, fontFamily: fonts.mono, fontSize: 11, letterSpacing: '0.16em', textTransform: 'uppercase', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8 }}>
            <Icon d={ICONS.add} size={13} stroke={c.accent} /> Nieuwe sessie
          </button>
        </div>

        {/* Center HUD readout */}
        <div style={styles.centerWrap}>
          {/* Range strip header */}
          <div style={styles.rangeStrip}>
            <span>L 0</span>
            {Array.from({ length: 11 }, (_, i) => (
              <div key={i} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2 }}>
                <div style={styles.tick(i % 5 === 0 ? 8 : 4)} />
                {i % 5 === 0 && <span>{i}</span>}
              </div>
            ))}
            <span>10 R</span>
          </div>

          <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: 16 }}>
            <div>
              <div style={styles.label}><span style={styles.labelDot} /> Laatste sessie · S-0247</div>
              <div style={{ fontFamily: fonts.display, fontSize: 28, fontWeight: 600, color: c.text, marginTop: 6, letterSpacing: '-0.02em' }}>
                Walther LP500 · Luchtpistool 10m
              </div>
              <div style={{ fontSize: 12, color: c.muted, marginTop: 4 }}>SV Diemen · 08 mei · 60 schoten · 32 minuten</div>
            </div>
            <div style={{ textAlign: 'right' }}>
              <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.16em' }}>FINAL</div>
              <div style={styles.bigNum}>
                <span style={styles.bigNumAccent}>547</span>
                <span style={styles.bigUnit}>/600</span>
              </div>
            </div>
          </div>

          {/* Reticle */}
          <div style={styles.reticleBox}>
            {/* Outer scope ring */}
            <svg width="100%" height="100%" viewBox="0 0 600 380" preserveAspectRatio="xMidYMid meet" style={{ position: 'absolute', inset: 0 }}>
              <circle cx="300" cy="190" r="170" fill="none" stroke={c.line} strokeWidth="1" />
              <circle cx="300" cy="190" r="178" fill="none" stroke={c.accent} strokeWidth="1" strokeDasharray="2 6" opacity="0.5" />
              {/* Range marks */}
              {[0, 90, 180, 270].map(a => {
                const rad = (a * Math.PI) / 180;
                const x1 = 300 + Math.cos(rad) * 168, y1 = 190 + Math.sin(rad) * 168;
                const x2 = 300 + Math.cos(rad) * 184, y2 = 190 + Math.sin(rad) * 184;
                return <line key={a} x1={x1} y1={y1} x2={x2} y2={y2} stroke={c.accent} strokeWidth="1.5" />;
              })}
              {/* Mil-dots */}
              {[-2, -1, 1, 2].map(k => (
                <g key={k}>
                  <circle cx={300 + k * 40} cy="190" r="1.5" fill={c.muted} />
                  <circle cx="300" cy={190 + k * 40} r="1.5" fill={c.muted} />
                </g>
              ))}
              {/* Center label */}
              <text x="300" y="22" textAnchor="middle" fill={c.muted} style={{ font: `9px ${fonts.mono}`, letterSpacing: '0.2em' }}>HIT PATTERN · 60 SHOTS</text>
            </svg>
            <div style={{ position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <TargetRings size={300} accent={c.accent} dim={c.text} ringStroke={1} />
            </div>
          </div>

          {/* Readout */}
          <div style={styles.readout}>
            <div style={styles.readoutCell(0)}>
              <div style={styles.label}>Beste schot</div>
              <div style={styles.readoutVal}>10.4</div>
            </div>
            <div style={styles.readoutCell(1)}>
              <div style={styles.label}>Groep</div>
              <div style={styles.readoutVal}>22<span style={{ fontSize: 12, color: c.muted }}> mm</span></div>
            </div>
            <div style={styles.readoutCell(2)}>
              <div style={styles.label}>Tienen</div>
              <div style={styles.readoutVal}>18<span style={{ fontSize: 12, color: c.muted }}>/60</span></div>
            </div>
            <div style={styles.readoutCell(3)}>
              <div style={styles.label}>Gem. cadans</div>
              <div style={styles.readoutVal}>32<span style={{ fontSize: 12, color: c.muted }}> sec</span></div>
            </div>
          </div>
        </div>

        {/* Right panel — AI + trend */}
        <div style={{ ...styles.panel, ...styles.panelDivR }}>
          <div>
            <div style={styles.label}><span style={styles.labelDot} /> Trend · LP500 · 30d</div>
            <div style={{ ...styles.bigNum, marginTop: 8 }}>
              <span style={styles.bigNumAccent}>547</span>
              <span style={{ fontFamily: fonts.mono, fontSize: 16, color: c.accent, marginLeft: 8, fontWeight: 600 }}>▲ +2.1</span>
            </div>
            <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, marginTop: 4, letterSpacing: '0.04em' }}>Gem. score · vs vorige periode</div>
            <div style={{ marginTop: 12 }}>
              <Spark data={TREND_30D} w={252} h={56} color={c.accent} strokeW={2} />
              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 6, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>
                <span>10 APR</span><span>22 APR</span><span>08 MEI</span>
              </div>
            </div>
          </div>

          <window.BracketFrame palette={c} bordered={false} panel={false} rounded={4} padding={14} cornerSize={16} style={{ flex: 1, border: `1px solid ${c.accent}40`, background: `${c.accent}08`, display: 'flex', flexDirection: 'column', gap: 10 }}>

            <div style={{ ...styles.label, color: c.accent }}>
              <Icon d={ICONS.ai} size={11} stroke={c.accent} /> AI-reflectie · S-0247
            </div>
            <div style={{ fontSize: 13, lineHeight: 1.55, color: c.text }}>
              Sterke openingsserie. Vanaf schot 35 daalt gem. met <span style={{ color: c.accent, fontFamily: fonts.mono }}>−0.4</span>; concentratie­dipje rond minuut 22.
            </div>
            <div style={{ height: 1, background: c.line }} />
            <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 12 }}>
              <div style={{ display: 'flex', gap: 8 }}>
                <span style={{ ...styles.label, color: c.accent, minWidth: 70 }}>WENT WELL</span>
                <span>Polsstabiliteit, openingsritme</span>
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <span style={{ ...styles.label, minWidth: 70 }}>IMPROVE</span>
                <span>Ademritme reset rond schot 30</span>
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <span style={{ ...styles.label, minWidth: 70 }}>NEXT</span>
                <span>2× 10 min droogoefenen, standwerk</span>
              </div>
            </div>
            <button style={{ marginTop: 4, padding: '8px 12px', borderRadius: 4, border: 'none', background: c.accent, color: c.ctaText, fontFamily: fonts.mono, fontSize: 11, fontWeight: 700, letterSpacing: '0.16em', textTransform: 'uppercase', cursor: 'pointer', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6 }}>
              <Icon d={ICONS.chat} size={12} stroke={c.ctaText} sw={2} /> Vraag coach
            </button>
          </window.BracketFrame>
        </div>
      </div>

      {/* Status bar */}
      <div style={styles.statusbar}>
        <div style={styles.sbCell}><span style={{ color: c.accent }}>●</span> SYNC OK</div>
        <div style={styles.sbCell}>SELF-HOSTED · v3.2.1</div>
        <div style={styles.sbCell}>QUEUE 0/3</div>
        <div style={{ ...styles.sbCell, marginLeft: 'auto', borderRight: 'none', borderLeft: `1px solid ${c.line}` }}>WM-4 EXPORT READY · 247 SESSIES</div>
      </div>
    </div>
  );
}

window.TacticalHUD = TacticalHUD;
