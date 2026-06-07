// "Nieuwe sessie" wizard — a full modal that drops over the app.
// Renders the app dimmed behind, modal floating, current step = scoring.

function NewSessionWizard({ palette, fonts }) {
  const c = palette;
  const a = window.aStyles(c, fonts);
  const { RangeConsole, Reticle, Icon, ICONS, TargetRings } = window;

  const steps = [
    { n: 1, label: 'Wapen',     done: true },
    { n: 2, label: 'Sessie',    done: true },
    { n: 3, label: 'Schoten',   current: true },
    { n: 4, label: 'Notities',  done: false },
  ];

  // Mock: 14 shots logged into a 60-shot LP10 session
  const logged = [10.1, 9.8, 10.3, 9.5, 10.0, 9.9, 9.7, 10.2, 9.6, 9.4, 9.8, 10.1, 9.7, 9.5];
  const total = logged.reduce((s, v) => s + v, 0);

  return (
    <div style={{ width: '100%', height: '100%', position: 'relative', overflow: 'hidden', background: c.bg }}>
      {/* Dimmed app behind */}
      <div style={{ position: 'absolute', inset: 0, opacity: 0.35, filter: 'blur(2px)', pointerEvents: 'none' }}>
        <RangeConsole palette={c} fonts={fonts} />
      </div>
      <div style={{ position: 'absolute', inset: 0, background: `${c.bg}cc` }} />

      {/* Modal */}
      <div style={{
        position: 'absolute', top: '50%', left: '50%', transform: 'translate(-50%, -50%)',
        width: 960, maxWidth: '92%', maxHeight: '92%',
        background: c.panel, border: `1px solid ${c.line}`, borderRadius: 16,
        boxShadow: `0 40px 80px ${c.bg}aa, 0 0 0 1px ${c.line}`,
        display: 'flex', flexDirection: 'column', overflow: 'hidden',
        color: c.text, fontFamily: fonts.body,
      }}>
        {/* Watermark in modal corner */}
        <div style={{ position: 'absolute', top: -40, right: -60, opacity: 0.08, pointerEvents: 'none' }}>
          <Reticle size={260} color={c.accent} stroke={1} dot />
        </div>

        {/* Header */}
        <div style={{ padding: '22px 28px 18px', borderBottom: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 18 }}>
          <div>
            <div style={a.label}>NIEUWE SESSIE · STAP 3 VAN 4</div>
            <h2 style={{ ...a.h2, fontSize: 20, marginTop: 4 }}>Log je schoten</h2>
          </div>
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
            <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.16em' }}>14:18 · BAAN 3</div>
            <button style={{ width: 30, height: 30, borderRadius: 8, border: `1px solid ${c.line}`, background: c.bg, color: c.muted, fontSize: 16, cursor: 'pointer' }}>×</button>
          </div>
        </div>

        {/* Stepper */}
        <div style={{ padding: '14px 28px', borderBottom: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 12 }}>
          {steps.map((s, i) => (
            <React.Fragment key={s.n}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                <div style={{
                  width: 24, height: 24, borderRadius: '50%',
                  background: s.done ? c.accent : s.current ? `${c.accent}1f` : 'transparent',
                  border: `1.5px solid ${s.done || s.current ? c.accent : c.line}`,
                  color: s.done ? c.ctaText : c.accent,
                  display: 'flex', alignItems: 'center', justifyContent: 'center',
                  fontFamily: fonts.mono, fontSize: 11, fontWeight: 700,
                }}>
                  {s.done ? <Icon d={ICONS.check} size={13} stroke={c.ctaText} sw={2.4} /> : s.n}
                </div>
                <div style={{ fontSize: 13, fontWeight: s.current ? 600 : 500, color: s.current ? c.text : c.muted }}>{s.label}</div>
              </div>
              {i < steps.length - 1 && (
                <div style={{ flex: 1, height: 1, background: c.line, position: 'relative' }}>
                  {s.done && <div style={{ position: 'absolute', inset: 0, background: c.accent }} />}
                </div>
              )}
            </React.Fragment>
          ))}
        </div>

        {/* Body */}
        <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1fr 280px', minHeight: 0 }}>
          {/* Left: logging area */}
          <div style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0 }}>
            {/* Progress row */}
            <div style={{ display: 'flex', alignItems: 'flex-end', gap: 24 }}>
              <div>
                <div style={a.label}>VOORTGANG</div>
                <div style={{ display: 'flex', alignItems: 'baseline', gap: 6, marginTop: 6 }}>
                  <div style={{ fontFamily: fonts.mono, fontSize: 36, fontWeight: 600, color: c.text, lineHeight: 1, letterSpacing: '-0.02em' }}>{logged.length}</div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 14, color: c.muted }}>/ 60</div>
                </div>
              </div>
              <div>
                <div style={a.label}>LOPENDE SCORE</div>
                <div style={{ fontFamily: fonts.mono, fontSize: 36, fontWeight: 600, color: c.accent, lineHeight: 1, marginTop: 6, letterSpacing: '-0.02em' }}>
                  {total.toFixed(1)}
                </div>
              </div>
              <div>
                <div style={a.label}>GEM. PER SCHOT</div>
                <div style={{ fontFamily: fonts.mono, fontSize: 36, fontWeight: 600, color: c.text, lineHeight: 1, marginTop: 6, letterSpacing: '-0.02em' }}>
                  {(total / logged.length).toFixed(2)}
                </div>
              </div>
              <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10, padding: '6px 10px', borderRadius: 6, background: `${c.accent}10`, border: `1px solid ${c.accent}33` }}>
                <div style={{ width: 6, height: 6, borderRadius: '50%', background: c.accent, animation: 'pulse 1.2s infinite' }} />
                <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.16em', color: c.accent }}>OPNAME ACTIEF</div>
              </div>
            </div>

            {/* Shot strip */}
            <div style={{ padding: 14, border: `1px solid ${c.line}`, borderRadius: 8, background: c.bg }}>
              <div style={{ display: 'flex', alignItems: 'flex-end', gap: 2, height: 56 }}>
                {logged.map((v, i) => {
                  const h = ((v - 7.5) / 3) * 48 + 6;
                  return <div key={i} title={`#${i+1}: ${v}`} style={{ flex: '0 0 9px', height: h, background: v >= 10 ? c.accent : `${c.muted}66`, borderRadius: 1 }} />;
                })}
                {/* Placeholder slots for remaining shots */}
                {Array.from({ length: 60 - logged.length }).map((_, i) => (
                  <div key={`ph${i}`} style={{ flex: '0 0 9px', height: 6, background: c.line, borderRadius: 1, opacity: 0.45 }} />
                ))}
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.08em' }}>
                <span>SCHOT 1</span><span>15</span><span>30</span><span>45</span><span>60</span>
              </div>
            </div>

            {/* Score numpad */}
            <div>
              <div style={a.label}>VOLGEND SCHOT · #{logged.length + 1}</div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6, 1fr)', gap: 6, marginTop: 10 }}>
                {['10.9','10.8','10.7','10.6','10.5','10.4','10.3','10.2','10.1','10.0','9.9','9.8','9.7','9.6','9.5','9.0','8','0'].map((s, i) => {
                  const isTen = s.startsWith('10');
                  const isMiss = s === '0';
                  return (
                    <div key={s} style={{
                      padding: '11px 0', borderRadius: 6,
                      background: isTen ? `${c.accent}14` : isMiss ? `${c.warn}14` : c.bg,
                      border: `1px solid ${isTen ? c.accent + '33' : isMiss ? c.warn + '33' : c.line}`,
                      color: isTen ? c.accent : isMiss ? c.warn : c.text,
                      textAlign: 'center', fontFamily: fonts.mono, fontSize: 14, fontWeight: 600, letterSpacing: '-0.01em',
                      cursor: 'pointer',
                    }}>
                      {s}
                    </div>
                  );
                })}
              </div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginTop: 14, fontSize: 11, color: c.muted }}>
                <span style={{ fontFamily: fonts.mono, padding: '3px 6px', border: `1px solid ${c.line}`, borderRadius: 3 }}>↑↓</span>
                <span>navigeer</span>
                <span style={{ fontFamily: fonts.mono, padding: '3px 6px', border: `1px solid ${c.line}`, borderRadius: 3, marginLeft: 8 }}>↵</span>
                <span>bevestig</span>
                <span style={{ fontFamily: fonts.mono, padding: '3px 6px', border: `1px solid ${c.line}`, borderRadius: 3, marginLeft: 8 }}>U</span>
                <span>terug</span>
                <span style={{ marginLeft: 'auto', fontFamily: fonts.mono, letterSpacing: '0.08em' }}>OF VUL HANDMATIG IN ↓</span>
              </div>
              <div style={{ marginTop: 10, display: 'flex', alignItems: 'center', gap: 10 }}>
                <input readOnly value="10." style={{
                  flex: 1, padding: '12px 14px', borderRadius: 8,
                  background: c.bg, border: `2px solid ${c.accent}`, color: c.text,
                  fontFamily: fonts.mono, fontSize: 18, fontWeight: 600, letterSpacing: '-0.01em',
                  outline: 'none',
                }} />
                <button style={{ padding: '12px 18px', borderRadius: 8, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, fontSize: 14, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6 }}>
                  Bevestig <Icon d={ICONS.arrow} size={14} stroke={c.ctaText} sw={2.2} />
                </button>
              </div>
            </div>
          </div>

          {/* Right: context */}
          <div style={{ padding: 24, borderLeft: `1px solid ${c.line}`, display: 'flex', flexDirection: 'column', gap: 16, minHeight: 0, overflow: 'auto', background: c.bg }}>
            <div>
              <div style={a.label}>SESSIE</div>
              <div style={{ fontSize: 14, fontWeight: 600, color: c.text, marginTop: 6 }}>Luchtpistool 10m</div>
              <div style={{ fontSize: 12, color: c.muted, fontFamily: fonts.mono, marginTop: 2 }}>SV Diemen · Baan 3</div>
            </div>

            <div>
              <div style={a.label}>WAPEN</div>
              <div style={{ marginTop: 6, padding: '10px 12px', borderRadius: 8, background: c.panel, border: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 10 }}>
                <Icon d={ICONS.weapon} size={16} stroke={c.accent} />
                <div style={{ flex: 1, minWidth: 0 }}>
                  <div style={{ fontSize: 13, fontWeight: 600, color: c.text }}>Walther LP500</div>
                  <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.02em' }}>4.5 mm · LP500-4421</div>
                </div>
              </div>
            </div>

            <div>
              <div style={a.label}>OPTIES</div>
              <div style={{ marginTop: 10, display: 'flex', flexDirection: 'column', gap: 8 }}>
                {[
                  { l: 'AI-reflectie',     on: true },
                  { l: 'Decimaal-notatie', on: true },
                  { l: 'Toon ringen-view', on: false },
                ].map((o, i) => (
                  <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 10, fontSize: 13, color: c.text }}>
                    <div style={{
                      width: 30, height: 18, borderRadius: 999,
                      background: o.on ? c.accent : c.line,
                      position: 'relative',
                      transition: 'background .15s',
                    }}>
                      <div style={{
                        position: 'absolute', top: 2,
                        left: o.on ? 14 : 2,
                        width: 14, height: 14, borderRadius: '50%',
                        background: c.ctaText,
                      }} />
                    </div>
                    <span>{o.l}</span>
                  </div>
                ))}
              </div>
            </div>

            <div style={{ marginTop: 'auto', padding: 12, background: `${c.accent}10`, border: `1px solid ${c.accent}33`, borderRadius: 8, position: 'relative' }}>
              <div style={{ position: 'absolute', top: -1, left: -1, width: 14, height: 14, borderTop: `1.5px solid ${c.accent}`, borderLeft: `1.5px solid ${c.accent}` }} />
              <div style={{ position: 'absolute', top: -1, right: -1, width: 14, height: 14, borderTop: `1.5px solid ${c.accent}`, borderRight: `1.5px solid ${c.accent}` }} />
              <div style={{ position: 'absolute', bottom: -1, left: -1, width: 14, height: 14, borderBottom: `1.5px solid ${c.accent}`, borderLeft: `1.5px solid ${c.accent}` }} />
              <div style={{ position: 'absolute', bottom: -1, right: -1, width: 14, height: 14, borderBottom: `1.5px solid ${c.accent}`, borderRight: `1.5px solid ${c.accent}` }} />
              <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.accent, letterSpacing: '0.14em' }}>● AI TIJDENS SESSIE</div>
              <div style={{ fontSize: 12, lineHeight: 1.5, color: c.text, marginTop: 6 }}>
                Goed bezig — schot 1–10 zit boven je gemiddelde. Hou je cadans rond 30s aan.
              </div>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div style={{ padding: '14px 28px', borderTop: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 10, background: c.panel }}>
          <button style={a.btn}>← Vorige stap</button>
          <button style={{ ...a.btn, marginLeft: 'auto' }}>Pauze</button>
          <button style={a.btn}>Opslaan & later</button>
          <button style={a.btnPrim}>Sessie afronden <Icon d={ICONS.arrow} size={13} stroke={c.ctaText} sw={2.2} /></button>
        </div>
      </div>
    </div>
  );
}

window.NewSessionWizard = NewSessionWizard;
