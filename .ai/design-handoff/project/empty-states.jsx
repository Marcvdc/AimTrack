// Empty states + first-run welcome — 4 small artboards.

function EmptyStates({ palette, fonts, state }) {
  const c = palette;
  const { Reticle, ATMark, AimTrackLogo, Wordmark, ASidebar, ATopbar, Icon, ICONS } = window;
  const a = window.aStyles(c, fonts);

  // Shared screen-frame with the sidebar+topbar so empty state lands in
  // the actual product chrome (looks real, not a toy).
  const Frame = ({ children, active, crumbs, title, sub }) => (
    <div style={a.root}>
      <ASidebar palette={c} fonts={fonts} active={active} />
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        <ATopbar palette={c} fonts={fonts} crumbs={crumbs}>
          <button style={a.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} />Nieuw</button>
        </ATopbar>
        <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden', minHeight: 0 }}>
          <div style={{ padding: '24px 24px 4px' }}>
            <h1 style={a.h1}>{title}</h1>
            {sub && <p style={a.sub}>{sub}</p>}
          </div>
          <div style={{ flex: 1, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 32, position: 'relative', minHeight: 0 }}>
            {children}
          </div>
        </div>
      </div>
    </div>
  );

  // ── State 1: First-run welcome ────────────────────────────
  if (state === 'first-run') {
    return (
      <div style={{ width: '100%', height: '100%', background: c.bg, color: c.text, fontFamily: fonts.body, display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative', overflow: 'hidden' }}>
        {/* Big watermark behind */}
        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0.06 }}>
          <Reticle size={680} color={c.accent} stroke={1} dot />
        </div>
        <div style={{ position: 'relative', maxWidth: 520, textAlign: 'center' }}>
          <div style={{ display: 'inline-flex', alignItems: 'center', justifyContent: 'center', width: 90, height: 90, borderRadius: 22, background: c.panel, border: `1px solid ${c.line}` }}>
            <AimTrackLogo size={56} color={c.accent} />
          </div>
          <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.22em', color: c.accent, marginTop: 18 }}>● WELKOM</div>
          <h1 style={{ fontFamily: fonts.display, fontSize: 44, fontWeight: 600, letterSpacing: '-0.025em', margin: '12px 0 14px', color: c.text, lineHeight: 1.1 }}>
            Klaar voor je <span style={{ color: c.accent }}>eerste sessie?</span>
          </h1>
          <p style={{ fontSize: 15, color: c.muted, lineHeight: 1.6, maxWidth: 420, margin: '0 auto' }}>
            Drie korte stappen — wapen toevoegen, baan kiezen, je eerste 60 schoten loggen. Daarna kijkt AimTrack mee.
          </p>
          <div style={{ marginTop: 32, display: 'flex', flexDirection: 'column', gap: 10, maxWidth: 380, margin: '32px auto 0' }}>
            {[
              { n: 1, t: 'Voeg je eerste wapen toe', done: true },
              { n: 2, t: 'Maak je profiel af',       done: false, current: true },
              { n: 3, t: 'Log je eerste sessie',     done: false },
            ].map(s => (
              <div key={s.n} style={{
                padding: '12px 14px', borderRadius: 10,
                background: s.current ? `${c.accent}10` : c.panel,
                border: `1px solid ${s.current ? c.accent + '40' : c.line}`,
                display: 'flex', alignItems: 'center', gap: 12, textAlign: 'left',
              }}>
                <div style={{
                  width: 24, height: 24, borderRadius: '50%',
                  background: s.done ? c.accent : 'transparent',
                  border: `1.5px solid ${s.done || s.current ? c.accent : c.line}`,
                  color: s.done ? c.ctaText : c.accent,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontFamily: fonts.mono, fontSize: 11, fontWeight: 700,
                  flex: '0 0 24px',
                }}>
                  {s.done ? <Icon d={ICONS.check} size={13} stroke={c.ctaText} sw={2.4} /> : s.n}
                </div>
                <div style={{ flex: 1, fontSize: 13, color: c.text, fontWeight: s.current ? 600 : 500 }}>{s.t}</div>
                {s.current && <Icon d={ICONS.arrow} size={14} stroke={c.accent} />}
              </div>
            ))}
          </div>
          <div style={{ marginTop: 28, display: 'flex', gap: 10, justifyContent: 'center' }}>
            <button style={{ padding: '12px 22px', borderRadius: 10, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, fontSize: 14, cursor: 'pointer' }}>
              Verder waar ik was
            </button>
            <button style={{ padding: '12px 22px', borderRadius: 10, border: `1px solid ${c.line}`, background: 'transparent', color: c.text, fontSize: 14, cursor: 'pointer' }}>
              Demo-data inladen
            </button>
          </div>
        </div>
      </div>
    );
  }

  // ── State 2: No sessions yet ──────────────────────────────
  if (state === 'no-sessions') {
    return (
      <Frame active="sessies" crumbs={['LOG', 'SESSIES']} title="Sessies" sub="Hier komen je trainingen.">
        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0.05 }}>
          <Reticle size={460} color={c.accent} stroke={1} dot />
        </div>
        <div style={{ textAlign: 'center', maxWidth: 420, position: 'relative' }}>
          <div style={{ display: 'inline-flex', width: 64, height: 64, borderRadius: 18, background: c.panel, border: `1px solid ${c.line}`, alignItems: 'center', justifyContent: 'center', color: c.accent }}>
            <Icon d={ICONS.session} size={28} stroke={c.accent} sw={1.7} />
          </div>
          <h2 style={{ ...a.h1, marginTop: 16 }}>Nog geen sessies gelogd</h2>
          <p style={{ ...a.sub, marginTop: 10, fontSize: 14, maxWidth: 360, marginLeft: 'auto', marginRight: 'auto' }}>
            Log je eerste training in ongeveer 30 seconden. Discipline, wapen, baan, score — klaar.
          </p>
          <div style={{ marginTop: 22, display: 'flex', gap: 10, justifyContent: 'center' }}>
            <button style={a.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} sw={2.2} />Eerste sessie loggen</button>
            <button style={a.btn}>Demo-data inladen</button>
          </div>
          <div style={{ marginTop: 28, padding: 14, background: c.panel, border: `1px solid ${c.line}`, borderRadius: 10, fontSize: 12, color: c.muted, textAlign: 'left' }}>
            <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.accent, letterSpacing: '0.16em' }}>💡 TIP</div>
            <div style={{ marginTop: 6, color: c.text }}>Heb je een papieren logboek? Foto's uploaden kan straks ook — AimTrack leest de score uit.</div>
          </div>
        </div>
      </Frame>
    );
  }

  // ── State 3: No weapons yet ──────────────────────────────
  if (state === 'no-weapons') {
    return (
      <Frame active="wapens" crumbs={['LOG', 'WAPENS']} title="Wapens" sub="Voeg je wapens toe om sessies te kunnen loggen.">
        <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0.05 }}>
          <Reticle size={460} color={c.accent} stroke={1} dot />
        </div>
        <div style={{ textAlign: 'center', maxWidth: 480, position: 'relative' }}>
          <div style={{ display: 'inline-flex', width: 64, height: 64, borderRadius: 18, background: c.panel, border: `1px solid ${c.line}`, alignItems: 'center', justifyContent: 'center', color: c.accent }}>
            <Icon d={ICONS.weapon} size={28} stroke={c.accent} sw={1.7} />
          </div>
          <h2 style={{ ...a.h1, marginTop: 16 }}>Voeg je eerste wapen toe</h2>
          <p style={{ ...a.sub, marginTop: 10, fontSize: 14, maxWidth: 380, marginLeft: 'auto', marginRight: 'auto' }}>
            Elk wapen krijgt zijn eigen overzicht — sessies, schotaantal, onderhoud, kalibratie.
          </p>

          {/* 3 starter templates */}
          <div style={{ marginTop: 22, display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10 }}>
            {[
              { type: 'Luchtpistool', caliber: '4.5 mm', popular: true },
              { type: 'Pistool',      caliber: '9 mm' },
              { type: 'Vrij pistool', caliber: '.22 LR' },
            ].map((w, i) => (
              <div key={i} style={{
                padding: 14, borderRadius: 10,
                background: w.popular ? `${c.accent}10` : c.panel,
                border: `1px solid ${w.popular ? c.accent + '40' : c.line}`,
                textAlign: 'left', cursor: 'pointer',
              }}>
                <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>SJABLOON</div>
                <div style={{ fontSize: 13, fontWeight: 600, color: c.text, marginTop: 4 }}>{w.type}</div>
                <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, marginTop: 2 }}>{w.caliber}</div>
                {w.popular && <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.accent, letterSpacing: '0.14em', marginTop: 6 }}>● MEEST GEBRUIKT</div>}
              </div>
            ))}
          </div>

          <div style={{ marginTop: 18, display: 'flex', gap: 10, justifyContent: 'center' }}>
            <button style={a.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} sw={2.2} />Voeg wapen toe</button>
            <button style={a.btn}>Importeer uit register</button>
          </div>
        </div>
      </Frame>
    );
  }

  // ── State 4: No AI reflection yet (or AI disabled) ──────
  return (
    <Frame active="coach" crumbs={['INZICHT', 'AI-COACH']} title="AI-coach" sub="Je persoonlijke coach, gevoed door je eigen data.">
      <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0.05 }}>
        <Reticle size={460} color={c.accent} stroke={1} dot />
      </div>
      <div style={{ textAlign: 'center', maxWidth: 480, position: 'relative' }}>
        <div style={{ display: 'inline-flex', width: 64, height: 64, borderRadius: 18, background: c.panel, border: `1px solid ${c.accent}40`, alignItems: 'center', justifyContent: 'center' }}>
          <Icon d={ICONS.ai} size={28} stroke={c.accent} sw={1.7} />
        </div>
        <h2 style={{ ...a.h1, marginTop: 16 }}>De coach heeft 3 sessies nodig</h2>
        <p style={{ ...a.sub, marginTop: 10, fontSize: 14, maxWidth: 380, marginLeft: 'auto', marginRight: 'auto' }}>
          Met minimaal 3 gelogde sessies kan AimTrack patronen herkennen en zinvolle reflecties geven.
        </p>

        {/* Progress */}
        <div style={{ marginTop: 22, padding: 16, background: c.panel, border: `1px solid ${c.line}`, borderRadius: 10, textAlign: 'left' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
            <div style={{ fontFamily: fonts.mono, fontSize: 26, fontWeight: 600, color: c.text, letterSpacing: '-0.02em' }}>1<span style={{ color: c.muted, fontSize: 14 }}>/3</span></div>
            <div style={{ flex: 1 }}>
              <div style={{ height: 6, background: c.line, borderRadius: 3, overflow: 'hidden' }}>
                <div style={{ height: '100%', width: '33%', background: c.accent }} />
              </div>
              <div style={{ fontSize: 11, color: c.muted, marginTop: 6, fontFamily: fonts.mono, letterSpacing: '0.04em' }}>NOG 2 SESSIES TE GAAN</div>
            </div>
          </div>
        </div>

        <div style={{ marginTop: 18, display: 'flex', gap: 10, justifyContent: 'center' }}>
          <button style={a.btnPrim}><Icon d={ICONS.add} size={13} stroke={c.ctaText} sw={2.2} />Log volgende sessie</button>
          <button style={a.btn}>Hoe werkt de AI?</button>
        </div>
      </div>
    </Frame>
  );
}

window.EmptyStates = EmptyStates;
