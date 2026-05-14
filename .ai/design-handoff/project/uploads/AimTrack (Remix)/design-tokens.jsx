// Design tokens reference card — one canvas of every primitive a developer
// needs to build AimTrack consistently. Colors / spacing / radius / type /
// components, with values readable at a glance.

function DesignTokens({ palette, fonts }) {
  const c = palette;
  const { Wordmark, AimTrackLogo, Icon, ICONS, Reticle, ATMark, Spark, TargetRings } = window;

  // ── small helpers ────────────────────────────────────────────────
  const sec = {
    padding: '28px 32px',
    borderBottom: `1px solid ${c.line}`,
  };
  const secHead = {
    display: 'flex', alignItems: 'baseline', gap: 14, marginBottom: 22,
  };
  const secKicker = {
    fontFamily: fonts.mono, fontSize: 10, color: c.muted,
    letterSpacing: '0.22em', textTransform: 'uppercase',
  };
  const secTitle = {
    fontFamily: fonts.display, fontSize: 24, fontWeight: 600,
    color: c.text, letterSpacing: '-0.015em', margin: 0,
  };
  const tokenLabel = {
    fontFamily: fonts.mono, fontSize: 10, color: c.muted,
    letterSpacing: '0.12em', textTransform: 'uppercase',
  };
  const tokenName = {
    fontFamily: fonts.mono, fontSize: 12, color: c.text,
    letterSpacing: '0.02em',
  };
  const tokenVal = {
    fontFamily: fonts.mono, fontSize: 11, color: c.muted,
    letterSpacing: '0.02em',
  };

  // ── data ────────────────────────────────────────────────────────
  const COLOR_TOKENS = [
    { name: '--bg',       val: c.bg,      desc: 'achtergrond' },
    { name: '--panel',    val: c.panel,   desc: 'kaart / module' },
    { name: '--panel-2',  val: c.panel2,  desc: 'subtiele hover / inset' },
    { name: '--line',     val: c.line,    desc: '1px scheidingen' },
    { name: '--text',     val: c.text,    desc: 'primaire tekst' },
    { name: '--muted',    val: c.muted,   desc: 'secundaire tekst' },
    { name: '--accent',   val: c.accent,  desc: 'CTA / data-highlight' },
    { name: '--accent-2', val: c.accent2, desc: 'links / 2e datakanaal' },
    { name: '--warn',     val: c.warn,    desc: 'fout / dip-markering' },
    { name: '--cta-text', val: c.ctaText, desc: 'tekst op --accent' },
  ];

  const SPACING = [
    { name: '--space-1', val: '4px' },
    { name: '--space-2', val: '8px' },
    { name: '--space-3', val: '12px' },
    { name: '--space-4', val: '16px' },
    { name: '--space-5', val: '24px' },
    { name: '--space-6', val: '32px' },
    { name: '--space-7', val: '48px' },
  ];

  const RADIUS = [
    { name: '--r-sm',  val: '4px',  desc: 'badge / tick' },
    { name: '--r-md',  val: '6px',  desc: 'button / nav-item' },
    { name: '--r-lg',  val: '8px',  desc: 'card / panel' },
    { name: '--r-xl',  val: '12px', desc: 'modal / hero' },
    { name: '--r-2xl', val: '18px', desc: 'mobile card' },
    { name: '--r-pill',val: '999px',desc: 'chip / tag' },
  ];

  const TYPE = [
    { kind: 'display', sizes: [
      { name: 'display/xl', size: 64, lh: 1.02, wt: 600 },
      { name: 'display/lg', size: 44, lh: 1.05, wt: 600 },
      { name: 'display/md', size: 28, lh: 1.1,  wt: 600 },
    ]},
    { kind: 'sans', sizes: [
      { name: 'heading',    size: 22, lh: 1.2,  wt: 600 },
      { name: 'subheading', size: 17, lh: 1.3,  wt: 600 },
      { name: 'body',       size: 13, lh: 1.5,  wt: 500 },
      { name: 'small',      size: 12, lh: 1.45, wt: 500 },
      { name: 'micro',      size: 11, lh: 1.4,  wt: 500 },
    ]},
    { kind: 'mono', sizes: [
      { name: 'data/xl',  size: 44, lh: 1, wt: 600 },
      { name: 'data/lg',  size: 26, lh: 1, wt: 600 },
      { name: 'data/md',  size: 18, lh: 1, wt: 600 },
      { name: 'label',    size: 10, lh: 1, wt: 500, ls: '0.18em', up: true },
    ]},
  ];

  // ── render ───────────────────────────────────────────────────────
  return (
    <div style={{
      width: '100%', minHeight: '100%',
      background: c.bg, color: c.text,
      fontFamily: fonts.body, fontSize: 13, lineHeight: 1.5,
      overflow: 'auto',
    }}>
      {/* Header */}
      <div style={{ padding: '36px 32px 28px', borderBottom: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 20 }}>
        <Wordmark size={32} color={c.text} accent={c.accent} />
        <div style={{ marginLeft: 8 }}>
          <div style={secKicker}>DESIGN TOKENS · v1.0</div>
          <div style={{ fontFamily: fonts.display, fontSize: 26, fontWeight: 600, letterSpacing: '-0.015em', marginTop: 4 }}>Bouwstenen voor AimTrack</div>
        </div>
        <div style={{ marginLeft: 'auto', textAlign: 'right' }}>
          <div style={tokenLabel}>HUIDIG THEME</div>
          <div style={{ fontFamily: fonts.mono, fontSize: 12, color: c.accent, marginTop: 4 }}>{c.name || 'custom'} · {c.bg === '#f6f5f1' ? 'light' : 'dark'}</div>
        </div>
      </div>

      {/* ── Colors ─────────────────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Kleuren</h2>
          <div style={secKicker}>10 TOKENS · OKLCH-FRIENDLY</div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 12 }}>
          {COLOR_TOKENS.map(t => (
            <div key={t.name} style={{ borderRadius: 8, border: `1px solid ${c.line}`, overflow: 'hidden', background: c.panel }}>
              <div style={{ height: 64, background: t.val, position: 'relative' }}>
                {t.name === '--accent' && (
                  <div style={{ position: 'absolute', top: 8, right: 8, padding: '2px 6px', background: c.ctaText, color: t.val, fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.12em', borderRadius: 3, fontWeight: 700 }}>BRAND</div>
                )}
              </div>
              <div style={{ padding: '10px 12px' }}>
                <div style={tokenName}>{t.name}</div>
                <div style={tokenVal}>{t.val.toUpperCase()}</div>
                <div style={{ fontSize: 11, color: c.muted, marginTop: 4 }}>{t.desc}</div>
              </div>
            </div>
          ))}
        </div>

        <div style={{ marginTop: 22, padding: 16, background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8 }}>
          <div style={tokenLabel}>ALPHA-VARIANTEN · GEBRUIK ALS HEX+ALPHA</div>
          <div style={{ display: 'flex', gap: 8, marginTop: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            {[
              { suf: '', alpha: 'FF', label: '100%' },
              { suf: 'CC', alpha: 'CC', label: '80%' },
              { suf: '80', alpha: '80', label: '50%' },
              { suf: '40', alpha: '40', label: '25%' },
              { suf: '1F', alpha: '1F', label: '12%' },
              { suf: '0A', alpha: '0A', label: '4%' },
            ].map(v => (
              <div key={v.suf} style={{ flex: 1, padding: '12px 10px', borderRadius: 6, background: c.accent + v.alpha, color: c.ctaText, textAlign: 'center', fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.1em', fontWeight: 600 }}>
                {v.label}<br/><span style={{ fontWeight: 400, opacity: 0.7 }}>+{v.suf || '00'}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── Type ───────────────────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Typografie</h2>
          <div style={secKicker}>3 ROLLEN · DISPLAY · SANS · MONO (DATA)</div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
          {TYPE.map(t => (
            <div key={t.kind} style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel }}>
              <div style={tokenLabel}>{t.kind === 'display' ? `DISPLAY · ${fonts.display.split(',')[0].replace(/['"]/g,'')}` : t.kind === 'sans' ? `SANS · ${fonts.body.split(',')[0].replace(/['"]/g,'')}` : `MONO · ${fonts.mono.split(',')[0].replace(/['"]/g,'')}`}</div>
              <div style={{ marginTop: 14, display: 'flex', flexDirection: 'column', gap: 14 }}>
                {t.sizes.map(s => (
                  <div key={s.name}>
                    <div style={tokenName}>{s.name} · {s.size}/{Math.round(s.size * s.lh)} · {s.wt}</div>
                    <div style={{
                      marginTop: 4,
                      fontFamily: t.kind === 'display' ? fonts.display : t.kind === 'mono' ? fonts.mono : fonts.body,
                      fontSize: s.size, lineHeight: s.lh, fontWeight: s.wt,
                      letterSpacing: s.ls || (t.kind === 'display' ? '-0.02em' : 0),
                      textTransform: s.up ? 'uppercase' : 'none',
                      color: c.text,
                    }}>
                      {t.kind === 'mono'
                        ? (s.up ? 'SCHOTAANTAL' : '547.3')
                        : t.kind === 'display'
                          ? 'Scherp in beeld'
                          : 'Sessies van vandaag'}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* ── Spacing & radius ───────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Ruimte & radius</h2>
          <div style={secKicker}>4-PUNTS BASIS · 7 SPACING STAPPEN · 6 RADII</div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: '1.3fr 1fr', gap: 28 }}>
          <div style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel }}>
            <div style={tokenLabel}>SPACING</div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 14 }}>
              {SPACING.map(s => (
                <div key={s.name} style={{ display: 'grid', gridTemplateColumns: '120px 60px 1fr', alignItems: 'center', gap: 12 }}>
                  <div style={tokenName}>{s.name}</div>
                  <div style={tokenVal}>{s.val}</div>
                  <div style={{ height: 14, background: c.accent, width: s.val, borderRadius: 2 }} />
                </div>
              ))}
            </div>
          </div>
          <div style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel }}>
            <div style={tokenLabel}>BORDER RADIUS</div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginTop: 14 }}>
              {RADIUS.map(r => (
                <div key={r.name} style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 6 }}>
                  <div style={{
                    width: 56, height: 56,
                    background: c.panel2,
                    border: `1px solid ${c.line}`,
                    borderRadius: r.val,
                  }} />
                  <div style={tokenName}>{r.name}</div>
                  <div style={tokenVal}>{r.val}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ── Components ─────────────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Componenten</h2>
          <div style={secKicker}>BASIS SET · FILAMENT-COMPATIBEL</div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }}>
          {/* Buttons */}
          <div style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={tokenLabel}>BUTTONS</div>
            <button style={{ padding: '8px 14px', borderRadius: 6, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, fontSize: 12, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}>
              <Icon d={ICONS.add} size={13} stroke={c.ctaText} sw={2.2} /> Primary
            </button>
            <button style={{ padding: '8px 14px', borderRadius: 6, border: `1px solid ${c.line}`, background: 'transparent', color: c.text, fontSize: 12, cursor: 'pointer' }}>Secondary</button>
            <button style={{ padding: '8px 14px', borderRadius: 6, border: `1px solid ${c.accent}40`, background: `${c.accent}10`, color: c.accent, fontSize: 12, cursor: 'pointer' }}>Ghost · accent</button>
          </div>

          {/* Badges */}
          <div style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={tokenLabel}>BADGES</div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '3px 7px', borderRadius: 4, fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.08em', textTransform: 'uppercase', background: `${c.accent}1f`, color: c.accent, border: `1px solid ${c.accent}33` }}>● OK</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '3px 7px', borderRadius: 4, fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.08em', textTransform: 'uppercase', background: `${c.warn}1a`, color: c.warn, border: `1px solid ${c.warn}33` }}>● OPEN</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, padding: '3px 7px', borderRadius: 4, fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.08em', textTransform: 'uppercase', background: `${c.muted}14`, color: c.muted, border: `1px solid ${c.line}` }}>● DRAFT</span>
            </div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '4px 10px', border: `1px solid ${c.line}`, borderRadius: 999, fontFamily: fonts.mono, fontSize: 11, color: c.muted }}>chip</span>
              <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, padding: '4px 10px', borderRadius: 999, background: `${c.accent}14`, color: c.accent, fontFamily: fonts.mono, fontSize: 11, border: `1px solid ${c.accent}40` }}>chip · on</span>
            </div>
          </div>

          {/* Input */}
          <div style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={tokenLabel}>INPUTS</div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '7px 10px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6, fontSize: 12, color: c.muted, fontFamily: fonts.mono }}>
              <Icon d={ICONS.search} size={12} stroke={c.muted} /> Zoek…
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '7px 10px', background: c.bg, border: `2px solid ${c.accent}`, borderRadius: 6, fontSize: 12, color: c.text, fontFamily: fonts.body }}>
              547<span style={{ marginLeft: 'auto', width: 1, height: 14, background: c.accent, animation: 'pulse 1s infinite' }} />
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '7px 10px', background: c.bg, border: `1px solid ${c.warn}88`, borderRadius: 6, fontSize: 12, color: c.warn, fontFamily: fonts.body }}>
              foutmelding
            </div>
          </div>

          {/* Stat card */}
          <div style={{ padding: 18, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel }}>
            <div style={tokenLabel}>STAT</div>
            <div style={{ ...tokenLabel, marginTop: 12, color: c.muted }}>SCORE</div>
            <div style={{ fontFamily: fonts.mono, fontSize: 28, fontWeight: 600, color: c.text, letterSpacing: '-0.02em', marginTop: 6 }}>547<span style={{ fontSize: 12, color: c.muted, marginLeft: 4 }}>/600</span></div>
            <div style={{ marginTop: 6, fontSize: 11, color: c.accent }}>▲ +5 vs gem.</div>
          </div>
        </div>
      </section>

      {/* ── Iconography ───────────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Iconografie</h2>
          <div style={secKicker}>STROKE-BASED · 1.6PX · 24X24</div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(8, 1fr)', gap: 10 }}>
          {Object.entries(ICONS).map(([k, d]) => (
            <div key={k} style={{ padding: '14px 8px', border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 8 }}>
              <Icon d={d} size={22} stroke={c.text} sw={1.7} />
              <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.04em' }}>{k}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ── Brand marks ─────────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Brand marks</h2>
          <div style={secKicker}>LOGO · MONOGRAM · RETICLE · WORDMARK</div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16 }}>
          <div style={{ padding: 24, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14 }}>
            <AimTrackLogo size={80} color={c.accent} />
            <div style={tokenName}>Logo · primair</div>
            <div style={tokenVal}>--accent op --bg</div>
          </div>
          <div style={{ padding: 24, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14 }}>
            <ATMark size={70} color={c.text} />
            <div style={tokenName}>AT-monogram</div>
            <div style={tokenVal}>compact / favicon</div>
          </div>
          <div style={{ padding: 24, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14 }}>
            <Reticle size={80} color={c.accent} stroke={1.5} dot />
            <div style={tokenName}>Reticle</div>
            <div style={tokenVal}>grafisch element T1/T3</div>
          </div>
          <div style={{ padding: 24, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 14 }}>
            <Wordmark size={42} color={c.text} accent={c.accent} />
            <div style={tokenName}>Wordmark</div>
            <div style={tokenVal}>nav / footer</div>
          </div>
        </div>
      </section>

      {/* ── Data viz ─────────────────────────────────────────── */}
      <section style={sec}>
        <div style={secHead}>
          <h2 style={secTitle}>Data-viz vocabulaire</h2>
          <div style={secKicker}>3 PRIMITIEVEN · OVERAL HERGEBRUIKT</div>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
          <div style={{ padding: 20, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
            <TargetRings size={140} accent={c.accent} dim={c.text} />
            <div style={tokenName}>Hit-pattern</div>
            <div style={tokenVal}>10-ringen · accent op tiens</div>
          </div>
          <div style={{ padding: 20, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel }}>
            <div style={tokenLabel}>SPARKLINE</div>
            <div style={{ marginTop: 14 }}><Spark w={280} h={70} color={c.accent} strokeW={2} /></div>
            <div style={{ ...tokenName, marginTop: 10 }}>Score-trend</div>
            <div style={tokenVal}>--accent · gradient fill onder</div>
          </div>
          <div style={{ padding: 20, border: `1px solid ${c.line}`, borderRadius: 8, background: c.panel }}>
            <div style={tokenLabel}>SHOT-STRIP</div>
            <div style={{ marginTop: 14, display: 'flex', alignItems: 'flex-end', gap: 2, height: 60 }}>
              {Array.from({length: 30}, (_, i) => {
                const v = 9 + Math.sin(i * 0.4) * 1.2 + (Math.random() - 0.5) * 0.6;
                const h = ((v - 7.5) / 3) * 54 + 4;
                const ten = v >= 10;
                const dip = i >= 14 && i <= 18;
                return <div key={i} style={{ flex: 1, height: h, background: ten ? c.accent : dip ? c.warn + 'aa' : c.muted + '66', borderRadius: 1 }} />;
              })}
            </div>
            <div style={{ ...tokenName, marginTop: 10 }}>Schot-voor-schot</div>
            <div style={tokenVal}>tiens=accent · dip=warn</div>
          </div>
        </div>
      </section>

      {/* ── Footer ─────────────────────────────────────────── */}
      <div style={{ padding: '20px 32px', display: 'flex', alignItems: 'center', gap: 14 }}>
        <ATMark size={18} color={c.muted} />
        <div style={tokenVal}>AimTrack design tokens · v1.0 · alle waardes als CSS-variabelen exporteerbaar</div>
        <div style={{ marginLeft: 'auto', fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.14em' }}>BUILD-READY · FILAMENT 3.x / TAILWIND</div>
      </div>
    </div>
  );
}

window.DesignTokens = DesignTokens;
