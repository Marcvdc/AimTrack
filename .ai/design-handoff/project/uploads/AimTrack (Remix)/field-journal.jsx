// Variation 2: "Field Journal" — narrative, journal-style entries
// Calmer dark, generous spacing, serif-leaning display, AI as conversation.

function FieldJournal({ palette, fonts }) {
  const c = palette;
  const { SESSIONS, WEAPONS, TargetRings, Spark, Wordmark, AimTrackLogo, Icon, ICONS } = window;

  const styles = {
    root: {
      width: '100%', height: '100%', display: 'flex',
      background: c.bg, color: c.text,
      fontFamily: fonts.body, fontSize: 14, lineHeight: 1.55,
    },
    side: {
      width: 76, flex: '0 0 76px',
      borderRight: `1px solid ${c.line}`,
      background: c.bg,
      display: 'flex', flexDirection: 'column',
      alignItems: 'center', padding: '20px 0',
      gap: 4,
    },
    rail: (active) => ({
      width: 44, height: 44, borderRadius: 12,
      display: 'flex', alignItems: 'center', justifyContent: 'center',
      color: active ? c.accent : c.muted,
      background: active ? `${c.accent}14` : 'transparent',
      border: `1px solid ${active ? c.accent + '40' : 'transparent'}`,
      cursor: 'pointer',
    }),

    main: { flex: 1, display: 'flex', minWidth: 0 },
    content: { flex: 1, padding: '32px 40px 40px', overflow: 'hidden', minWidth: 0 },
    aside: { width: 320, flex: '0 0 320px', borderLeft: `1px solid ${c.line}`, padding: '24px 24px 24px', display: 'flex', flexDirection: 'column', gap: 18, background: c.panel },

    header: { display: 'flex', alignItems: 'flex-end', gap: 24, marginBottom: 28 },
    h1: { fontFamily: fonts.display, fontSize: 38, fontWeight: 500, letterSpacing: '-0.025em', margin: 0, color: c.text, lineHeight: 1.05 },
    h1Mark: { color: c.accent, fontStyle: 'italic', fontWeight: 400 },
    sub: { color: c.muted, fontSize: 13, margin: 0 },

    monthRow: { display: 'flex', alignItems: 'center', gap: 18, fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.14em', textTransform: 'uppercase', marginBottom: 18 },
    monthLine: { flex: 1, height: 1, background: c.line },

    entry: {
      display: 'grid', gridTemplateColumns: '92px 1fr',
      gap: 20, padding: '18px 0',
      borderBottom: `1px solid ${c.line}`,
    },
    eDate: {
      fontFamily: fonts.display, fontSize: 28, color: c.text,
      letterSpacing: '-0.02em', lineHeight: 1, fontWeight: 500,
    },
    eDay: { fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.18em', textTransform: 'uppercase', marginTop: 6 },

    eBody: { display: 'flex', flexDirection: 'column', gap: 10, minWidth: 0 },
    eHead: { display: 'flex', alignItems: 'baseline', gap: 12, flexWrap: 'wrap' },
    eTitle: { fontFamily: fonts.display, fontSize: 19, fontWeight: 500, color: c.text, letterSpacing: '-0.01em' },
    eMeta: { fontSize: 12, color: c.muted, fontFamily: fonts.mono, letterSpacing: '0.04em' },
    eNote: { fontSize: 14, color: c.text, lineHeight: 1.55, opacity: 0.88 },

    eRow: { display: 'flex', alignItems: 'center', gap: 18, marginTop: 4, flexWrap: 'wrap' },
    chip: { display: 'inline-flex', alignItems: 'center', gap: 6, padding: '4px 10px', border: `1px solid ${c.line}`, borderRadius: 999, fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.04em' },
    chipAccent: { background: `${c.accent}14`, borderColor: `${c.accent}40`, color: c.accent },
    score: { fontFamily: fonts.display, fontSize: 30, color: c.accent, fontWeight: 500, letterSpacing: '-0.02em', lineHeight: 1, marginLeft: 'auto' },

    asideHead: { display: 'flex', alignItems: 'center', gap: 8, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.18em', textTransform: 'uppercase' },
    asideTitle: { fontFamily: fonts.display, fontSize: 22, fontWeight: 500, color: c.text, letterSpacing: '-0.01em', margin: '4px 0 0' },

    coachCard: { background: c.bg, border: `1px solid ${c.line}`, borderRadius: 14, padding: 18, display: 'flex', flexDirection: 'column', gap: 12 },
    bubble: (mine) => ({
      maxWidth: '92%',
      alignSelf: mine ? 'flex-end' : 'flex-start',
      background: mine ? `${c.accent}14` : c.panel2,
      color: mine ? c.text : c.text,
      padding: '10px 13px', borderRadius: 12,
      borderTopRightRadius: mine ? 4 : 12,
      borderTopLeftRadius: mine ? 12 : 4,
      fontSize: 13, lineHeight: 1.5,
      border: `1px solid ${mine ? c.accent + '30' : c.line}`,
    }),
    bubbleLabel: { fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em', textTransform: 'uppercase', marginBottom: 4 },
    coachInput: { display: 'flex', alignItems: 'center', gap: 8, padding: '10px 12px', background: c.panel2, border: `1px solid ${c.line}`, borderRadius: 10, color: c.muted, fontSize: 13 },

    weapon: { display: 'flex', alignItems: 'center', gap: 12, padding: '10px 0', borderBottom: `1px solid ${c.line}` },
    wMark: { width: 36, height: 36, borderRadius: 10, background: c.panel2, color: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center' },
  };

  const groupedSessions = [
    { month: 'Mei 2026', items: SESSIONS.filter(s => s.date.includes('mei')) },
    { month: 'April 2026', items: SESSIONS.filter(s => s.date.includes('apr')) },
  ];

  return (
    <div style={styles.root}>
      {/* Slim rail */}
      <aside style={styles.side}>
        <div style={{ marginBottom: 18 }}>
          <AimTrackLogo size={32} color={c.accent} />
        </div>
        <div style={styles.rail(true)}><Icon d={ICONS.session} sw={1.7} /></div>
        <div style={styles.rail()}><Icon d={ICONS.weapon} sw={1.7} /></div>
        <div style={styles.rail()}><Icon d={ICONS.spark} sw={1.7} /></div>
        <div style={styles.rail()}><Icon d={ICONS.ai} sw={1.7} /></div>
        <div style={styles.rail()}><Icon d={ICONS.export} sw={1.7} /></div>
        <div style={{ flex: 1 }} />
        <div style={styles.rail()}><Icon d={ICONS.shield} sw={1.7} /></div>
        <div style={{ width: 36, height: 36, borderRadius: 10, background: c.panel2, color: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', fontFamily: fonts.mono, fontWeight: 700, fontSize: 12, marginTop: 4 }}>MV</div>
      </aside>

      {/* Main */}
      <div style={styles.main}>
        <div style={styles.content}>
          <div style={styles.header}>
            <div style={{ flex: 1 }}>
              <div style={{ ...styles.eDay, marginBottom: 8 }}>Logboek · 2026</div>
              <h1 style={styles.h1}>Mei <span style={styles.h1Mark}>—</span> 14 sessies, <br/>2.920 schoten gelogd.</h1>
              <p style={{ ...styles.sub, marginTop: 12 }}>Je gemiddelde luchtpistool­score klimt voor de derde maand op rij. Beste serie tot nu toe: 552 op 29 april.</p>
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button style={{ padding: '10px 14px', borderRadius: 10, border: `1px solid ${c.line}`, background: 'transparent', color: c.text, display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer' }}>
                <Icon d={ICONS.export} size={14} /> Export WM-4
              </button>
              <button style={{ padding: '10px 16px', borderRadius: 10, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, cursor: 'pointer' }}>
                <Icon d={ICONS.add} size={14} stroke={c.ctaText} /> Nieuwe sessie
              </button>
            </div>
          </div>

          {/* Journal entries */}
          {groupedSessions.map((g, gi) => (
            <div key={gi} style={{ marginBottom: 6 }}>
              <div style={styles.monthRow}>
                <span>{g.month}</span>
                <div style={styles.monthLine} />
                <span>{g.items.length} sessies</span>
              </div>
              {g.items.map((s) => (
                <div key={s.id} style={styles.entry}>
                  <div>
                    <div style={styles.eDate}>{s.date.split(' ')[0]}</div>
                    <div style={styles.eDay}>{s.day} · {s.date.split(' ')[1]}</div>
                  </div>
                  <div style={styles.eBody}>
                    <div style={styles.eHead}>
                      <div style={styles.eTitle}>{s.discipline}</div>
                      <div style={styles.eMeta}>{s.range} · {s.weapon}</div>
                      <div style={styles.score}>{s.score}</div>
                    </div>
                    <div style={styles.eNote}>"{s.note}"</div>
                    <div style={styles.eRow}>
                      <span style={styles.chip}><Icon d={ICONS.target} size={11} /> {s.shots} schoten</span>
                      <span style={styles.chip}>Beste {s.best}</span>
                      <span style={styles.chip}>Groep {s.group} mm</span>
                      {s.ai && <span style={{ ...styles.chip, ...styles.chipAccent }}><Icon d={ICONS.ai} size={11} /> AI-reflectie</span>}
                      {!s.ai && <span style={styles.chip}>Reflectie open</span>}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ))}
        </div>

        {/* Right rail: AI coach */}
        <aside style={styles.aside}>
          <div>
            <div style={styles.asideHead}>
              <Icon d={ICONS.ai} size={12} /> AI-coach
              <span style={{ marginLeft: 'auto', color: c.accent }}>● online</span>
            </div>
            <div style={styles.asideTitle}>Vraag iets over <br/>je laatste sessies.</div>
          </div>

          <div style={styles.coachCard}>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div style={styles.bubble(true)}>
                <div style={styles.bubbleLabel}>Jij</div>
                Waarom zakt mijn score altijd rond schot 35?
              </div>
              <div style={styles.bubble(false)}>
                <div style={styles.bubbleLabel}>AimTrack</div>
                In je laatste 6 LP10-sessies daalt het gemiddelde tussen schot 30–40 met 0.3–0.5. Tijdstip valt rond minuut 20 — een typisch concentratie­dipje.
              </div>
              <div style={styles.bubble(false)}>
                <div style={styles.bubbleLabel}>AimTrack</div>
                Probeer een micro-pauze van 30s na schot 30, en reset je ademritme. Wil je dit als trainings­doel zetten?
              </div>
            </div>
            <div style={styles.coachInput}>
              <Icon d={ICONS.chat} size={14} stroke={c.muted} />
              <span style={{ flex: 1 }}>Stel een vraag…</span>
              <span style={{ fontFamily: fonts.mono, fontSize: 10, padding: '1px 5px', border: `1px solid ${c.line}`, borderRadius: 3 }}>↵</span>
            </div>
          </div>

          <div>
            <div style={styles.asideHead}><Icon d={ICONS.weapon} size={12} /> Wapens</div>
            <div style={{ marginTop: 6 }}>
              {WEAPONS.map(w => (
                <div key={w.id} style={styles.weapon}>
                  <div style={styles.wMark}><Icon d={ICONS.target} size={18} sw={1.6} /></div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 600, fontSize: 13, color: c.text }}>{w.name}</div>
                    <div style={{ fontSize: 11, color: c.muted, fontFamily: fonts.mono }}>{w.caliber} · {w.shots} schoten</div>
                  </div>
                  <Spark data={[w.avg-8,w.avg-4,w.avg-6,w.avg-2,w.avg,w.avg+w.trend]} w={48} h={22} color={w.trend>=0 ? c.accent : c.warn} fill={false} />
                </div>
              ))}
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}

window.FieldJournal = FieldJournal;
