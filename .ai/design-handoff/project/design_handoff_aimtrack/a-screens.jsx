// Three additional screens for the "A · Range Console" direction:
//   • SessionDetail — one session, deep dive (shot map, shot strip, AI)
//   • WeaponDetail  — one weapon, history & calibration
//   • AICoachView   — full-page chat with the coach
//
// All three reuse aStyles + ASidebar + ATopbar so they read as one product.

const { useState } = React;

// ───────────────────────────────────────────────────────────────────────
// SessionDetail
// ───────────────────────────────────────────────────────────────────────
function SessionDetail({ palette, fonts }) {
  const c = palette;
  const a = window.aStyles(c, fonts);
  const { ASidebar, ATopbar, TargetRings, RING_HITS, Icon, ICONS, Spark } = window;

  // 60 mock shots: scores between 8.1–10.6, with a dip around shot 35-40.
  const shots = Array.from({ length: 60 }, (_, i) => {
    let base = 9.4 + Math.sin(i * 0.7) * 0.35 + (Math.random() - 0.5) * 0.4;
    if (i >= 32 && i <= 42) base -= 0.5;
    if (i < 8) base += 0.25;
    return +Math.max(7.8, Math.min(10.7, base)).toFixed(1);
  });
  const series = [
    shots.slice(0, 10).reduce((s, v) => s + v, 0),
    shots.slice(10, 20).reduce((s, v) => s + v, 0),
    shots.slice(20, 30).reduce((s, v) => s + v, 0),
    shots.slice(30, 40).reduce((s, v) => s + v, 0),
    shots.slice(40, 50).reduce((s, v) => s + v, 0),
    shots.slice(50, 60).reduce((s, v) => s + v, 0),
  ];

  return (
    <div style={a.root}>
      <ASidebar palette={c} fonts={fonts} active="sessies" />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <ATopbar palette={c} fonts={fonts} crumbs={['LOG', 'SESSIES', 'S-0247']}>
          <button style={a.btn}><Icon d={ICONS.export} size={13} />Exporteer</button>
          <button style={a.btn}>Vorige</button>
          <button style={a.btnPrim}>Volgende sessie<Icon d={ICONS.arrow} size={13} stroke={c.ctaText} /></button>
        </ATopbar>

        <div style={{ padding: 24, display: 'grid', gridTemplateColumns: '1fr 340px', gap: 16, flex: 1, overflow: 'hidden', minHeight: 0 }}>
          {/* Left column */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
            {/* Header card */}
            <div style={{ ...a.panel, padding: 20, display: 'flex', alignItems: 'flex-start', gap: 24, position: 'relative', overflow: 'hidden' }}>
              {/* T1 · reticle watermark */}
              <window.WatermarkBg palette={c} size={220} top={-60} right={-40} opacity={0.07} />
              {/* T4 · trust stamp — sessie is geverifieerd & klaar voor WM-4 */}
              <window.MonogramStamp palette={c} fonts={fonts} label="WM-4 OK" corner="top-right" />
              <div style={{ flex: 1, position: 'relative' }}>
                <div style={a.label}>SESSIE · S-0247 · woensdag 08 mei 2026</div>
                <h1 style={{ ...a.h1, fontSize: 26, marginTop: 6 }}>Luchtpistool 10m · 60 schoten</h1>
                <div style={{ display: 'flex', gap: 16, marginTop: 10, fontSize: 12, color: c.muted, flexWrap: 'wrap' }}>
                  <span><Icon d={ICONS.target} size={12} stroke={c.muted} style={{ display: 'inline', verticalAlign: -2, marginRight: 4 }} />SV Diemen · Baan 3</span>
                  <span>Walther LP500 · 4.5 mm</span>
                  <span>14:08 – 14:40 · 32 min</span>
                  <span>17 °C · vlak</span>
                </div>
              </div>
              <div style={{ textAlign: 'right', position: 'relative' }}>
                <div style={a.label}>EINDSCORE</div>
                <div style={{ fontFamily: fonts.mono, fontSize: 44, fontWeight: 600, color: c.accent, lineHeight: 1, letterSpacing: '-0.02em' }}>547</div>
                <div style={{ fontFamily: fonts.mono, fontSize: 12, color: c.accent, marginTop: 2 }}>▲ +5 vs gem.</div>
              </div>
            </div>

            {/* Stats row */}
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 12 }}>
              {[
                ['Beste schot', '10.4', null],
                ['Tienen', '18/60', null],
                ['Negens', '32/60', null],
                ['Groep', '22 mm', '−4 mm'],
                ['Gem. cadans', '32s', null],
              ].map(([l, v, sub]) => (
                <div key={l} style={a.stat}>
                  <div style={a.statLabel}>{l}</div>
                  <div style={a.statValue}>{v}</div>
                  {sub && <div style={{ ...a.statSub, color: c.accent }}>{sub}</div>}
                </div>
              ))}
            </div>

            {/* Series cards */}
            <div style={a.card}>
              <div style={a.cardHead}>
                <Icon d={ICONS.spark} size={14} stroke={c.accent} />
                <div style={a.cardTitle}>Series · 6 × 10 schoten</div>
                <div style={a.cardSub}>tot {series.reduce((s, v) => s + v, 0).toFixed(1)}</div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)' }}>
                {series.map((v, i) => {
                  const good = v >= 95;
                  return (
                    <div key={i} style={{ padding: 14, borderRight: i < 5 ? `1px solid ${c.line}` : 'none', display: 'flex', flexDirection: 'column', gap: 4 }}>
                      <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>SERIE {i + 1}</div>
                      <div style={{ fontFamily: fonts.mono, fontSize: 22, fontWeight: 600, color: good ? c.accent : c.text, letterSpacing: '-0.02em' }}>{v.toFixed(1)}</div>
                      <div style={{ height: 4, background: c.line, borderRadius: 2, position: 'relative', overflow: 'hidden' }}>
                        <div style={{ position: 'absolute', inset: 0, width: `${((v - 80) / 20) * 100}%`, background: good ? c.accent : c.muted }} />
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Shot strip */}
            <div style={a.card}>
              <div style={a.cardHead}>
                <Icon d={ICONS.target} size={14} stroke={c.accent} />
                <div style={a.cardTitle}>Schot-voor-schot</div>
                <div style={a.cardSub}>60 schoten</div>
              </div>
              <div style={{ padding: '14px 16px 16px' }}>
                <div style={{ display: 'flex', alignItems: 'flex-end', gap: 2, height: 80 }}>
                  {shots.map((v, i) => {
                    const h = ((v - 7.5) / 3) * 70 + 6;
                    const dip = i >= 32 && i <= 42;
                    return (
                      <div key={i} title={`#${i + 1}: ${v}`}
                        style={{
                          flex: 1,
                          height: h,
                          background: v >= 10 ? c.accent : dip ? c.warn + 'aa' : c.muted + '66',
                          borderRadius: 1,
                          minWidth: 4,
                        }}
                      />
                    );
                  })}
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.08em' }}>
                  <span>1</span><span>15</span><span>30</span><span>45</span><span>60</span>
                </div>
                <div style={{ display: 'flex', gap: 18, marginTop: 12, fontSize: 11, color: c.muted, fontFamily: fonts.mono }}>
                  <span><span style={{ display: 'inline-block', width: 8, height: 8, background: c.accent, marginRight: 6, verticalAlign: 0 }} />10+ ringen</span>
                  <span><span style={{ display: 'inline-block', width: 8, height: 8, background: c.warn + 'aa', marginRight: 6 }} />concentratiedip 33–42</span>
                  <span><span style={{ display: 'inline-block', width: 8, height: 8, background: c.muted + '66', marginRight: 6 }} />normaal</span>
                </div>
              </div>
            </div>
          </div>

          {/* Right column: hit map + AI reflection + notes */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
            <div style={a.card}>
              <div style={a.cardHead}>
                <Icon d={ICONS.target} size={14} stroke={c.accent} />
                <div style={a.cardTitle}>Hit-patroon</div>
                <div style={a.cardSub}>60 schoten</div>
              </div>
              <div style={{ padding: 16, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 12 }}>
                <TargetRings size={240} accent={c.accent} dim={c.text} hits={[
                  ...RING_HITS,
                  ...Array.from({length: 25}, () => ({ x: (Math.random()-0.5)*0.6, y: (Math.random()-0.5)*0.6, r: 9.0 + Math.random()*1 }))
                ]} scoreLabels />
                <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>
                  <span>X: −2.4 mm</span>
                  <span>Y: +1.8 mm</span>
                  <span>SD: 6.1 mm</span>
                </div>
              </div>
            </div>

            <window.BracketFrame palette={c} bordered panel rounded={8} padding={16} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 11, fontFamily: fonts.mono, letterSpacing: '0.12em', textTransform: 'uppercase', color: c.accent }}>
                <Icon d={ICONS.ai} size={12} stroke={c.accent} /> AI-reflectie
                <span style={{ marginLeft: 'auto', color: c.muted }}>gegenereerd 14:42</span>
              </div>
              <div style={{ fontSize: 13, lineHeight: 1.55, color: c.text }}>
                "Sterke opening: 5× tien op rij in de eerste serie. De 4e serie (schot 31–40) zakt naar <span style={{ color: c.warn, fontFamily: fonts.mono }}>87.2</span> — typisch concentratiedipje rond minuut 22 van je sessies."
              </div>
              <div style={{ height: 1, background: c.line }} />
              <div style={{ display: 'grid', gridTemplateColumns: '80px 1fr', gap: 6, fontSize: 12 }}>
                <div style={{ ...a.label, color: c.accent }}>STERK</div>
                <div>Openingsritme, polsstabiliteit</div>
                <div style={a.label}>VERBETER</div>
                <div>Micro-pauze rond schot 30, ademritme</div>
                <div style={a.label}>VOLGENDE</div>
                <div>2× 10 min droogoefenen · standwerk</div>
              </div>
              <div style={{ display: 'flex', gap: 6 }}>
                <button style={{ ...a.btnGhost, flex: 1, justifyContent: 'center' }}>
                  <Icon d={ICONS.chat} size={13} stroke={c.accent} /> Vraag coach
                </button>
                <button style={a.btn}>
                  <Icon d={ICONS.check} size={13} /> Markeer
                </button>
              </div>
            </window.BracketFrame>

            <div style={a.card}>
              <div style={a.cardHead}>
                <div style={a.cardTitle}>Eigen notitie</div>
              </div>
              <div style={{ padding: 16, fontSize: 13, lineHeight: 1.55, color: c.text, fontStyle: 'italic' }}>
                "Nieuwe handgreep gebruikt vanaf serie 3. Voelde stabieler maar in serie 4 begon mijn duim te verkrampen. Volgende keer kortere pauzes proberen."
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ───────────────────────────────────────────────────────────────────────
// WeaponDetail
// ───────────────────────────────────────────────────────────────────────
function WeaponDetail({ palette, fonts }) {
  const c = palette;
  const a = window.aStyles(c, fonts);
  const { ASidebar, ATopbar, Spark, TREND_30D, Icon, ICONS, SESSIONS } = window;

  return (
    <div style={a.root}>
      <ASidebar palette={c} fonts={fonts} active="wapens" />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <ATopbar palette={c} fonts={fonts} crumbs={['LOG', 'WAPENS', 'W-001 · LP500']}>
          <button style={a.btn}><Icon d={ICONS.export} size={13} />Card export</button>
          <button style={a.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} />Onderhoud loggen</button>
        </ATopbar>

        <div style={{ padding: 24, display: 'grid', gridTemplateColumns: '320px 1fr', gap: 16, flex: 1, overflow: 'hidden', minHeight: 0 }}>
          {/* Left: weapon ID card */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <div style={{ ...a.panel, padding: 20 }}>
              <div style={{
                aspectRatio: '16/10',
                background: `linear-gradient(135deg, ${c.panel2}, ${c.panel})`,
                border: `1px solid ${c.line}`,
                borderRadius: 6,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                color: c.muted, fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em',
                position: 'relative',
              }}>
                {/* Stylized pistol silhouette */}
                <svg width="65%" height="65%" viewBox="0 0 200 100" style={{ opacity: 0.7 }}>
                  <path d="M20 60 L160 60 L168 50 L175 50 L175 60 L185 60 L180 70 L155 70 L150 85 L130 85 L125 75 L40 75 L40 80 L25 80 Z" fill="none" stroke={c.accent} strokeWidth="1.5" />
                  <circle cx="55" cy="68" r="3" fill={c.accent} />
                  <line x1="40" y1="60" x2="40" y2="75" stroke={c.accent} strokeWidth="1" />
                </svg>
                <div style={{ position: 'absolute', top: 8, left: 8, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.18em' }}>FOTO</div>
              </div>

              <div style={{ marginTop: 14 }}>
                <div style={a.label}>LUCHTPISTOOL · 4.5 mm</div>
                <h1 style={{ ...a.h1, fontSize: 22, marginTop: 4 }}>Walther LP500</h1>
                <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, marginTop: 6 }}>SERIAL · LP500-4421</div>
              </div>

              <div style={{ height: 1, background: c.line, margin: '14px 0' }} />

              {[
                ['Status', 'Actief · WM-4', 'ok'],
                ['Aangeschaft', 'Feb 2024'],
                ['Laatste onderhoud', '12 apr 2026 · veerwissel'],
                ['Kaliber', '4.5 mm diabolo'],
                ['Gewicht', '950 g'],
              ].map(([k, v, kind]) => (
                <div key={k} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0', fontSize: 12 }}>
                  <div style={{ color: c.muted }}>{k}</div>
                  <div style={{ color: c.text, fontFamily: kind === 'ok' ? fonts.body : fonts.body }}>
                    {kind === 'ok' && <span style={{ color: c.accent }}>● </span>}{v}
                  </div>
                </div>
              ))}
            </div>

            <div style={{ ...a.card, padding: 16 }}>
              <div style={{ ...a.label, marginBottom: 10 }}>KALIBRATIE</div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 10 }}>
                <div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>KORREL</div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 16, color: c.text }}>+2</div>
                </div>
                <div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>VIZIER</div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 16, color: c.text }}>−1 R</div>
                </div>
                <div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>TREKKER</div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 16, color: c.text }}>520 g</div>
                </div>
                <div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>HANDGREEP</div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 16, color: c.text }}>M · v2</div>
                </div>
              </div>
            </div>
          </div>

          {/* Right: trend + sessions */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
              {[
                ['Sessies', '38', '+4 / mnd'],
                ['Schoten totaal', '2.140', null],
                ['Gem. score', '547.3', '▲ +2.1'],
                ['Beste', '552', '29 apr'],
              ].map(([l, v, sub], i) => (
                <div key={i} style={a.stat}>
                  <div style={a.statLabel}>{l}</div>
                  <div style={a.statValue}>{v}</div>
                  {sub && <div style={{ ...a.statSub, color: sub.startsWith('▲') || sub.startsWith('+') ? c.accent : c.muted }}>{sub}</div>}
                </div>
              ))}
            </div>

            <div style={a.card}>
              <div style={a.cardHead}>
                <Icon d={ICONS.spark} size={14} stroke={c.accent} />
                <div style={a.cardTitle}>Score-trend · alle sessies</div>
                <div style={a.cardSub}>Feb 2024 → mei 2026</div>
              </div>
              <div style={{ padding: 18 }}>
                <Spark data={[510, 515, 520, 522, 530, 528, 535, 540, 538, 542, 545, 547, 550, 548, 552, 547]} w={760} h={140} color={c.accent} strokeW={2} />
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 10, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>
                  <span>2024 Q1</span><span>Q3</span><span>2025 Q1</span><span>Q3</span><span>2026 Q1</span><span>NU</span>
                </div>
              </div>
            </div>

            <div style={a.card}>
              <div style={a.cardHead}>
                <Icon d={ICONS.session} size={14} stroke={c.accent} />
                <div style={a.cardTitle}>Sessies met dit wapen</div>
                <div style={a.cardSub}>5 recent · 38 totaal</div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '70px 1fr 90px 80px 90px 40px', padding: '8px 16px', fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.12em', textTransform: 'uppercase', color: c.muted, borderBottom: `1px solid ${c.line}` }}>
                <div>Datum</div><div>Discipline · Baan</div><div>Schoten</div><div>Score</div><div>Status</div><div></div>
              </div>
              {SESSIONS.filter(s => s.weapon === 'Walther LP500').map(s => (
                <div key={s.id} style={{ display: 'grid', gridTemplateColumns: '70px 1fr 90px 80px 90px 40px', padding: '12px 16px', borderBottom: `1px solid ${c.line}`, alignItems: 'center', fontSize: 12 }}>
                  <div style={{ fontFamily: fonts.mono, color: c.muted, fontSize: 11 }}>{s.date}</div>
                  <div>
                    <div style={{ fontWeight: 600, color: c.text }}>{s.discipline}</div>
                    <div style={{ fontSize: 11, color: c.muted }}>{s.range}</div>
                  </div>
                  <div style={{ fontFamily: fonts.mono, color: c.text }}>{s.shots}</div>
                  <div style={{ fontFamily: fonts.mono, color: c.accent, fontWeight: 600 }}>{s.score}</div>
                  <div>{s.ai && <span style={a.badge('ok')}><Icon d={ICONS.ai} size={10} /> AI</span>}</div>
                  <div style={{ display: 'flex', justifyContent: 'flex-end' }}><Icon d={ICONS.more} size={14} stroke={c.muted} /></div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ───────────────────────────────────────────────────────────────────────
// AICoachView — full-page chat
// ───────────────────────────────────────────────────────────────────────
function AICoachView({ palette, fonts }) {
  const c = palette;
  const a = window.aStyles(c, fonts);
  const { ASidebar, ATopbar, Icon, ICONS, Spark } = window;

  const messages = [
    { role: 'system', text: 'AI-coach geactiveerd · context: laatste 30 dagen, 14 sessies, focus op LP500' },
    { role: 'user',   text: 'Waarom zakt mijn score altijd rond schot 35?' },
    { role: 'ai',     text: 'In je laatste 6 LP10-sessies daalt het gemiddelde tussen schot 30–40 met 0.3–0.5 punt. Het tijdstip valt rond minuut 20 van je sessie — een typisch concentratiedipje.' },
    { role: 'ai',     text: '', attach: 'chart' },
    { role: 'user',   text: 'Wat raad je aan?' },
    { role: 'ai',     text: 'Drie dingen die voor jouw profiel werken:\n\n1. Micro-pauze van 30s na schot 30 — adem opnieuw opzetten\n2. Vaste cadans van ~30s per schot houden, niet versnellen\n3. Droogoefening 2× 10 min in de week, focus op standwerk' },
    { role: 'ai',     text: 'Zal ik dit als trainingsdoel voor mei toevoegen? Dan zie ik volgende sessie of het effect heeft.', attach: 'cta' },
  ];

  return (
    <div style={a.root}>
      <ASidebar palette={c} fonts={fonts} active="coach" />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <ATopbar palette={c} fonts={fonts} crumbs={['INZICHT', 'AI-COACH']}>
          <button style={a.btn}><Icon d={ICONS.cal} size={13} />Geschiedenis</button>
          <button style={a.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} />Nieuw gesprek</button>
        </ATopbar>

        <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '260px 1fr 280px', minHeight: 0 }}>
          {/* Chat list rail */}
          <div style={{ borderRight: `1px solid ${c.line}`, padding: '16px 12px', overflow: 'auto', display: 'flex', flexDirection: 'column', gap: 4 }}>
            <div style={{ ...a.label, padding: '4px 8px 10px' }}>RECENTE GESPREKKEN</div>
            {[
              { title: 'Concentratiedip schot 35', ts: '14:42', active: true, badge: 'live' },
              { title: 'Vergelijk LP500 vs CZ', ts: '4 mei' },
              { title: 'WM-4 export uitleg', ts: '2 mei' },
              { title: 'Trekker afstellen', ts: '28 apr' },
              { title: 'Adem-protocol opbouwen', ts: '22 apr' },
              { title: 'Match-routine plannen', ts: '19 apr' },
            ].map((t, i) => (
              <div key={i} style={{
                padding: '10px 10px',
                borderRadius: 6,
                background: t.active ? c.panel2 : 'transparent',
                border: `1px solid ${t.active ? c.line : 'transparent'}`,
                cursor: 'pointer',
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                  {t.badge && <span style={{ width: 6, height: 6, borderRadius: '50%', background: c.accent }} />}
                  <div style={{ fontSize: 12, color: c.text, fontWeight: t.active ? 600 : 500, flex: 1, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{t.title}</div>
                </div>
                <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, marginTop: 2, marginLeft: t.badge ? 12 : 0 }}>{t.ts}</div>
              </div>
            ))}
          </div>

          {/* Chat thread */}
          <div style={{ display: 'flex', flexDirection: 'column', minHeight: 0 }}>
            <div style={{ flex: 1, overflow: 'auto', padding: '28px 40px', display: 'flex', flexDirection: 'column', gap: 18 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.14em', textTransform: 'uppercase', marginBottom: 4 }}>
                <div style={{ flex: 1, height: 1, background: c.line }} />
                <span>10 MEI · 14:42</span>
                <div style={{ flex: 1, height: 1, background: c.line }} />
              </div>
              {messages.map((m, i) => {
                if (m.role === 'system') {
                  return (
                    <div key={i} style={{ alignSelf: 'center', padding: '4px 10px', border: `1px solid ${c.line}`, borderRadius: 999, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.08em' }}>
                      ● {m.text}
                    </div>
                  );
                }
                if (m.role === 'user') {
                  return (
                    <div key={i} style={{
                      alignSelf: 'flex-end', maxWidth: 560,
                      background: `${c.accent}14`, color: c.text,
                      padding: '12px 14px', borderRadius: 12,
                      border: `1px solid ${c.accent}33`,
                      fontSize: 13.5, lineHeight: 1.55,
                    }}>
                      {m.text}
                    </div>
                  );
                }
                // ai
                return (
                  <div key={i} style={{ alignSelf: 'flex-start', maxWidth: 620, display: 'flex', gap: 10 }}>
                    <div style={{ width: 28, height: 28, borderRadius: 6, background: c.panel2, border: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 28px', marginTop: 2 }}>
                      <Icon d={ICONS.ai} size={14} stroke={c.accent} />
                    </div>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                      {m.text && (
                        <div style={{
                          background: c.panel, color: c.text,
                          padding: '12px 14px', borderRadius: 12,
                          border: `1px solid ${c.line}`,
                          fontSize: 13.5, lineHeight: 1.6, whiteSpace: 'pre-line',
                        }}>{m.text}</div>
                      )}
                      {m.attach === 'chart' && (
                        <div style={{ padding: 14, border: `1px solid ${c.line}`, borderRadius: 12, background: c.panel }}>
                          <div style={a.label}>SCORE-DRIFT · LAATSTE 6 LP10 SESSIES · SCHOT 30–40</div>
                          <div style={{ marginTop: 10 }}>
                            <Spark data={[9.6, 9.5, 9.4, 9.2, 9.0, 8.9, 9.0, 9.1, 9.3, 9.4, 9.4]} w={520} h={70} color={c.warn} strokeW={2} />
                          </div>
                          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 6, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>
                            <span>SCHOT 30</span><span>35</span><span>40</span>
                          </div>
                        </div>
                      )}
                      {m.attach === 'cta' && (
                        <div style={{ display: 'flex', gap: 8 }}>
                          <button style={a.btnPrim}><Icon d={ICONS.check} size={13} stroke={c.ctaText} sw={2.2} />Voeg doel toe</button>
                          <button style={a.btn}>Misschien later</button>
                        </div>
                      )}
                    </div>
                  </div>
                );
              })}
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, color: c.muted, fontSize: 12, marginTop: 6 }}>
                <div style={{ width: 28, height: 28 }} />
                <div style={{ display: 'flex', gap: 4 }}>
                  <span style={{ width: 6, height: 6, borderRadius: '50%', background: c.accent, animation: 'pulse 1.2s infinite' }} />
                  <span style={{ width: 6, height: 6, borderRadius: '50%', background: c.accent, opacity: 0.6 }} />
                  <span style={{ width: 6, height: 6, borderRadius: '50%', background: c.accent, opacity: 0.3 }} />
                </div>
                <span style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.12em' }}>AI-COACH TYPT…</span>
              </div>
            </div>

            {/* Composer */}
            <div style={{ borderTop: `1px solid ${c.line}`, padding: '14px 20px', background: c.bg }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 14px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 10 }}>
                <Icon d={ICONS.chat} size={14} stroke={c.muted} />
                <span style={{ flex: 1, color: c.text, fontSize: 13 }}>Stel een vraag over je sessies, wapens, of doelen…</span>
                <button style={{ ...a.btnGhost, padding: '4px 10px' }}>
                  <Icon d={ICONS.session} size={11} stroke={c.accent} /> S-0247
                </button>
                <button style={{ ...a.btnPrim, padding: '6px 12px' }}>
                  <Icon d={ICONS.arrow} size={13} stroke={c.ctaText} sw={2} />
                </button>
              </div>
              <div style={{ display: 'flex', gap: 8, marginTop: 10, flexWrap: 'wrap' }}>
                {['Vergelijk met vorige maand', 'Wat trainen deze week?', 'Trekkerafstelling LP500', 'WM-4 status'].map(s => (
                  <button key={s} style={{ padding: '5px 10px', borderRadius: 999, border: `1px solid ${c.line}`, background: 'transparent', color: c.muted, fontSize: 11, fontFamily: fonts.mono, letterSpacing: '0.04em', cursor: 'pointer' }}>{s}</button>
                ))}
              </div>
            </div>
          </div>

          {/* Right context rail */}
          <div style={{ borderLeft: `1px solid ${c.line}`, padding: '20px 18px', display: 'flex', flexDirection: 'column', gap: 14, overflow: 'auto' }}>
            <div style={a.label}>CONTEXT IN GESPREK</div>
            <div style={{ ...a.panel, padding: 12, display: 'flex', alignItems: 'center', gap: 10 }}>
              <Icon d={ICONS.session} size={16} stroke={c.accent} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12, color: c.text, fontWeight: 600 }}>S-0247 · 08 mei</div>
                <div style={{ fontSize: 11, color: c.muted, fontFamily: fonts.mono }}>LP10 · 547 · LP500</div>
              </div>
              <span style={a.badge('ok')}>IN</span>
            </div>
            <div style={{ ...a.panel, padding: 12, display: 'flex', alignItems: 'center', gap: 10 }}>
              <Icon d={ICONS.weapon} size={16} stroke={c.muted} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 12, color: c.text, fontWeight: 600 }}>Walther LP500</div>
                <div style={{ fontSize: 11, color: c.muted, fontFamily: fonts.mono }}>38 sessies · 2140 schoten</div>
              </div>
              <span style={a.badge('ok')}>IN</span>
            </div>

            <div style={{ ...a.label, marginTop: 8 }}>VOORGESTELDE DOELEN</div>
            <div style={{ ...a.panel, padding: 12 }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
                <div style={{ width: 16, height: 16, borderRadius: 4, border: `1.5px solid ${c.accent}`, marginTop: 2 }} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 12, color: c.text, fontWeight: 600 }}>Micro-pauze schot 30</div>
                  <div style={{ fontSize: 11, color: c.muted, marginTop: 2 }}>30s adem-reset · 4 sessies</div>
                </div>
              </div>
            </div>
            <div style={{ ...a.panel, padding: 12 }}>
              <div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
                <div style={{ width: 16, height: 16, borderRadius: 4, border: `1.5px solid ${c.muted}`, marginTop: 2 }} />
                <div style={{ flex: 1 }}>
                  <div style={{ fontSize: 12, color: c.text, fontWeight: 600 }}>Cadans vasthouden</div>
                  <div style={{ fontSize: 11, color: c.muted, marginTop: 2 }}>30s ± 4s per schot</div>
                </div>
              </div>
            </div>

            <div style={{ ...a.label, marginTop: 8 }}>PRIVACY</div>
            <div style={{ fontSize: 11, color: c.muted, lineHeight: 1.6 }}>
              Antwoorden gegenereerd op je <span style={{ color: c.accent }}>eigen instance</span>. Geen data verlaat de server.
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { SessionDetail, WeaponDetail, AICoachView });
