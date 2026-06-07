// Logo system — reusable components that lock in the four roles for the
// AimTrack logo DNA:
//
//   T1 · WatermarkBg     — ambient reticle, "atmosphere"
//   T2 · RingMedaillon   — ring-as-frame, "showpiece" (one per screen max)
//   T3 · BracketFrame    — crosshair-tick corners, "emphasis" on AI/score cards
//   T4 · MonogramStamp   — AT mark, "verification" badge for trust moments
//
// Rule of thumb: T1 + max 1× T2 per screen; T3 marks AI emphasis;
// T4 marks auth/export trust moments. Never stack T3 + T4 on same card.

// ── T1 · WatermarkBg ────────────────────────────────────────────
// Drops an oversized reticle behind hero KPIs / page headers. The host
// container needs `position: relative; overflow: hidden`.
function WatermarkBg({
  palette: c,
  size = 220,
  opacity = 0.08,
  top = -40,
  right = -30,
  left,
  bottom,
  center,
  color,
  dot = true,
  stroke = 1,
}) {
  const Reticle = window.Reticle;
  if (center) {
    return (
      <div style={{
        position: 'absolute', inset: 0,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        pointerEvents: 'none', zIndex: 0, opacity,
      }}>
        <Reticle size={size} color={color || c.accent} stroke={stroke} opacity={1} dot={dot} />
      </div>
    );
  }
  const pos = {
    position: 'absolute',
    top: bottom != null ? 'auto' : top,
    right: left != null ? 'auto' : right,
    left: left != null ? left : 'auto',
    bottom: bottom != null ? bottom : 'auto',
    pointerEvents: 'none',
    zIndex: 0,
  };
  return (
    <div style={pos}>
      <Reticle size={size} color={color || c.accent} stroke={stroke} opacity={opacity} dot={dot} />
    </div>
  );
}

// ── T3 · BracketFrame ───────────────────────────────────────────
// Wraps children in a card with crosshair-tick corner brackets. Use this
// on AI-reflectie cards, score-emphasis blocks, "live" moments. The
// container is rendered for you, with the standard panel styling — but
// you can override with `style`.
function BracketFrame({
  palette: c,
  children,
  cornerSize = 14,
  cornerStroke = 1.5,
  cornerColor,
  bordered = true,
  panel = true,
  rounded = 8,
  padding = 16,
  style = {},
}) {
  const cc = cornerColor || c.accent;
  const corner = (vert, horiz) => ({
    position: 'absolute',
    [vert]: -1,
    [horiz]: -1,
    width: cornerSize,
    height: cornerSize,
    [`border${vert === 'top' ? 'Top' : 'Bottom'}`]: `${cornerStroke}px solid ${cc}`,
    [`border${horiz === 'left' ? 'Left' : 'Right'}`]: `${cornerStroke}px solid ${cc}`,
    pointerEvents: 'none',
  });
  return (
    <div style={{
      position: 'relative',
      background: panel ? c.panel : 'transparent',
      border: bordered ? `1px solid ${c.line}` : 'none',
      borderRadius: rounded,
      padding,
      ...style,
    }}>
      <div style={corner('top', 'left')} />
      <div style={corner('top', 'right')} />
      <div style={corner('bottom', 'left')} />
      <div style={corner('bottom', 'right')} />
      {children}
    </div>
  );
}

// ── T2 · RingMedaillon ──────────────────────────────────────────
// The logo ring around a score readout. Reserved: one per screen.
function RingMedaillon({
  palette: c,
  fonts,
  size = 200,
  label = 'SCORE',
  value = '547',
  sub = '/600',
  color,
  stroke = 1.5,
}) {
  const Reticle = window.Reticle;
  const ac = color || c.accent;
  return (
    <div style={{ position: 'relative', width: size, height: size }}>
      <Reticle size={size} color={ac} stroke={stroke} opacity={0.85} dot={false} />
      <div style={{
        position: 'absolute', inset: 0,
        display: 'flex', flexDirection: 'column',
        alignItems: 'center', justifyContent: 'center',
        textAlign: 'center',
      }}>
        <div style={{
          fontFamily: fonts.mono, fontSize: Math.max(9, size * 0.05),
          letterSpacing: '0.18em', color: c.muted, textTransform: 'uppercase',
        }}>{label}</div>
        <div style={{
          fontFamily: fonts.mono, fontSize: size * 0.22, fontWeight: 600,
          color: ac, lineHeight: 1, letterSpacing: '-0.02em',
          marginTop: size * 0.02,
        }}>{value}</div>
        {sub && (
          <div style={{
            fontFamily: fonts.mono, fontSize: Math.max(10, size * 0.055),
            color: c.muted, marginTop: 4,
          }}>{sub}</div>
        )}
      </div>
    </div>
  );
}

// ── T4 · MonogramStamp ──────────────────────────────────────────
// Tiny "stamp" with AT monogram + label. Use for WM-4 verified, signed
// export, audit-OK moments. Default: solid accent fill on top edge of a
// card (place inside a `position: relative` parent and pass `corner`).
function MonogramStamp({
  palette: c,
  fonts,
  label = 'VERIFIED',
  variant = 'solid',          // 'solid' | 'outline'
  size = 'sm',                // 'sm' | 'md'
  corner,                     // 'top-right' | 'top-left' | undefined (inline)
  color,
}) {
  const ATMark = window.ATMark;
  const ac = color || c.accent;
  const isSm = size === 'sm';
  const solid = variant === 'solid';

  const cornerStyle = corner === 'top-right'
    ? { position: 'absolute', top: -10, right: 16 }
    : corner === 'top-left'
    ? { position: 'absolute', top: -10, left: 16 }
    : null;

  return (
    <div style={{
      display: 'inline-flex', alignItems: 'center', gap: 6,
      padding: isSm ? '4px 8px' : '6px 10px',
      background: solid ? ac : 'transparent',
      border: solid ? 'none' : `1px solid ${ac}66`,
      borderRadius: 4,
      ...(cornerStyle || {}),
    }}>
      <ATMark size={isSm ? 12 : 16} color={solid ? c.ctaText : ac} />
      <span style={{
        fontFamily: fonts.mono, fontSize: isSm ? 9 : 10,
        letterSpacing: '0.16em', fontWeight: 700,
        color: solid ? c.ctaText : ac,
      }}>{label}</span>
    </div>
  );
}

// ── Reference card: the system, on one page ─────────────────────
function LogoSystemReference({ palette, fonts }) {
  const c = palette;
  const f = fonts;
  const { Reticle, ATMark, Icon, ICONS, Spark, AimTrackLogo, Wordmark } = window;

  const label = {
    fontFamily: f.mono, fontSize: 10, letterSpacing: '0.18em',
    textTransform: 'uppercase', color: c.muted,
  };
  const role = {
    fontFamily: f.mono, fontSize: 11, letterSpacing: '0.14em',
    color: c.accent,
  };
  const tName = {
    fontFamily: f.display, fontSize: 22, fontWeight: 600,
    color: c.text, letterSpacing: '-0.01em', margin: '6px 0 4px',
  };
  const body = {
    fontSize: 12.5, lineHeight: 1.55, color: c.muted, margin: 0,
  };
  const cell = {
    background: c.panel,
    border: `1px solid ${c.line}`,
    borderRadius: 10,
    padding: 20,
    display: 'flex', flexDirection: 'column', gap: 10,
    position: 'relative',
    overflow: 'hidden',
  };
  const usage = {
    fontFamily: f.mono, fontSize: 10, color: c.text,
    letterSpacing: '0.06em', display: 'flex', flexDirection: 'column', gap: 4,
    marginTop: 'auto',
  };
  const yes = (txt) => (
    <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 11, color: c.accent }}>
      <Icon d={ICONS.check} size={11} stroke={c.accent} sw={2.2} />
      <span style={{ color: c.text }}>{txt}</span>
    </div>
  );
  const no = (txt) => (
    <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 11 }}>
      <span style={{ width: 11, height: 11, position: 'relative', display: 'inline-block' }}>
        <span style={{ position: 'absolute', top: 5, left: 0, width: 11, height: 1.5, background: c.warn, transform: 'rotate(45deg)', transformOrigin: 'center' }} />
        <span style={{ position: 'absolute', top: 5, left: 0, width: 11, height: 1.5, background: c.warn, transform: 'rotate(-45deg)', transformOrigin: 'center' }} />
      </span>
      <span style={{ color: c.muted }}>{txt}</span>
    </div>
  );

  return (
    <div style={{
      width: '100%', height: '100%',
      background: c.bg, color: c.text,
      fontFamily: f.body,
      padding: 32,
      boxSizing: 'border-box',
      position: 'relative',
      overflow: 'hidden',
    }}>
      {/* Subtle bg reticle */}
      <div style={{ position: 'absolute', top: -80, right: -80, opacity: 0.04, pointerEvents: 'none' }}>
        <Reticle size={520} color={c.accent} stroke={1} dot />
      </div>

      {/* Title row */}
      <div style={{ position: 'relative', display: 'flex', alignItems: 'flex-end', gap: 18, marginBottom: 28 }}>
        <div style={{ flex: 1 }}>
          <div style={label}>0 · LOGO SYSTEM</div>
          <h1 style={{
            fontFamily: f.display, fontSize: 38, fontWeight: 600,
            letterSpacing: '-0.025em', margin: '8px 0 6px',
            color: c.text, lineHeight: 1.05,
          }}>
            Vier rollen, één <span style={{ color: c.accent }}>visuele taal</span>.
          </h1>
          <div style={{ fontSize: 14, color: c.muted, maxWidth: 720, lineHeight: 1.5 }}>
            Het logo (ring · crosshair-ticks · AT-monogram) keert in vier vormen terug.
            Elk heeft een aparte rol. Stapel ze niet op één plek — kies bewust.
          </div>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '12px 16px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8 }}>
          <AimTrackLogo size={40} color={c.accent} />
          <div>
            <div style={label}>BRON</div>
            <div style={{ fontSize: 13, fontWeight: 600, color: c.text, marginTop: 2 }}>AimTrack logomark</div>
          </div>
        </div>
      </div>

      {/* The four roles */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 16, position: 'relative' }}>
        {/* T1 */}
        <div style={cell}>
          <div style={{ position: 'absolute', top: -30, right: -30, opacity: 0.12, pointerEvents: 'none' }}>
            <Reticle size={180} color={c.accent} stroke={1} dot />
          </div>
          <div style={{ position: 'relative', zIndex: 1 }}>
            <div style={role}>T1 · AMBIENT</div>
            <h3 style={tName}>Reticle watermark</h3>
            <p style={body}>Grote, lichte reticle als achtergrond­textuur op hero-headers en KPI-blokken. Geeft het scherm een AimTrack-vibe zonder voorgrond te zijn.</p>
          </div>
          <div style={{ position: 'relative', zIndex: 1, marginTop: 8, padding: 14, background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6, overflow: 'hidden' }}>
            <div style={{ position: 'absolute', top: -20, right: -20, opacity: 0.12 }}>
              <Reticle size={110} color={c.accent} stroke={1} dot />
            </div>
            <div style={{ position: 'relative', fontFamily: f.mono, fontSize: 9, letterSpacing: '0.18em', color: c.muted }}>SESSIES / MEI</div>
            <div style={{ position: 'relative', fontFamily: f.mono, fontSize: 30, fontWeight: 600, color: c.text, lineHeight: 1, marginTop: 4, letterSpacing: '-0.02em' }}>
              14<span style={{ fontSize: 12, color: c.accent, marginLeft: 6 }}>▲+3</span>
            </div>
          </div>
          <div style={{ position: 'relative', zIndex: 1, ...usage, gap: 5 }}>
            {yes('Hero-headers · KPI-blokken')}
            {yes('Empty states · marketing hero')}
            {no('Kleine cards · tabel-rijen')}
          </div>
        </div>

        {/* T2 */}
        <div style={cell}>
          <div style={role}>T2 · SHOWPIECE</div>
          <h3 style={tName}>Ring-as-frame</h3>
          <p style={body}>De ring is letterlijk het kader rond één belangrijk getal. Eén keer per scherm — gereserveerd voor het hoofd­moment.</p>
          <div style={{ marginTop: 8, padding: 16, background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6, display: 'flex', justifyContent: 'center' }}>
            <RingMedaillon palette={c} fonts={f} size={130} label="SCORE" value="547" sub="/600" />
          </div>
          <div style={{ ...usage, gap: 5 }}>
            {yes('Score-medaillon · profiel-avatar')}
            {yes('Marketing hero')}
            {no('Twee per scherm')}
          </div>
        </div>

        {/* T3 */}
        <div style={cell}>
          <div style={role}>T3 · EMPHASIS</div>
          <h3 style={tName}>Crosshair brackets</h3>
          <p style={body}>De N/S/E/W ticks krimpen tot hoek­brackets. Reserveer voor AI-momenten en live-indicators — markeert: hier moet je naar kijken.</p>
          <div style={{ marginTop: 8, padding: 14, background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6 }}>
            <BracketFrame palette={c} bordered padding={12} rounded={6} style={{ background: c.panel2 }}>
              <div style={{ fontFamily: f.mono, fontSize: 9, letterSpacing: '0.16em', color: c.accent, marginBottom: 6 }}>● AI-REFLECTIE</div>
              <div style={{ fontSize: 12, lineHeight: 1.5, color: c.text }}>
                Sterke opening. Vanaf schot 35 daalt gem. met <span style={{ color: c.accent, fontFamily: f.mono }}>−0.4</span>.
              </div>
            </BracketFrame>
          </div>
          <div style={{ ...usage, gap: 5 }}>
            {yes('AI-reflectie · coach-output')}
            {yes('Live-sessie indicator')}
            {no('Decoratief op gewone cards')}
          </div>
        </div>

        {/* T4 */}
        <div style={cell}>
          <div style={role}>T4 · TRUST</div>
          <h3 style={tName}>Monogram stamp</h3>
          <p style={body}>Het AT-monogram als kleine stempel. Bevestigt: deze data klopt — WM-4 export, geverifieerd, ondertekend.</p>
          <div style={{ marginTop: 8, padding: 16, background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6, display: 'flex', flexDirection: 'column', gap: 10, alignItems: 'flex-start' }}>
            <MonogramStamp palette={c} fonts={f} label="WM-4 OK" />
            <MonogramStamp palette={c} fonts={f} label="VERIFIED" variant="outline" />
            <div style={{ display: 'flex', gap: 10, alignItems: 'center', width: '100%' }}>
              <ATMark size={18} color={c.accent} />
              <div style={{ flex: 1, height: 1, background: c.line }} />
              <div style={{ fontFamily: f.mono, fontSize: 9, color: c.muted, letterSpacing: '0.18em' }}>EXPORT · MEI</div>
              <div style={{ flex: 1, height: 1, background: c.line }} />
              <ATMark size={18} color={c.muted} />
            </div>
          </div>
          <div style={{ ...usage, gap: 5 }}>
            {yes('WM-4 export · audit badges')}
            {yes('Section dividers in journal')}
            {no('Naast T3 brackets op zelfde card')}
          </div>
        </div>
      </div>

      {/* Stacking rules */}
      <div style={{ position: 'relative', marginTop: 24, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
        <div style={{ background: c.panel, border: `1px solid ${c.line}`, borderRadius: 10, padding: 20 }}>
          <div style={role}>REGELS · OP ÉÉN SCHERM</div>
          <h3 style={{ fontFamily: f.display, fontSize: 18, fontWeight: 600, color: c.text, margin: '6px 0 12px' }}>Combineren, niet stapelen</h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            {[
              ['T1 ambient', '1× per scherm', c.accent],
              ['T2 showpiece', 'max 1× per scherm', c.accent],
              ['T3 brackets', 'meerdere, alleen op AI-cards', c.text],
              ['T4 stamp', 'meerdere, alleen op trust-momenten', c.text],
              ['T3 + T4 samen', 'niet op zelfde card', c.warn],
            ].map(([k, v, col]) => (
              <div key={k} style={{ display: 'grid', gridTemplateColumns: '130px 1fr', gap: 12, padding: '8px 12px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6, fontSize: 12.5 }}>
                <div style={{ fontFamily: f.mono, fontSize: 11, letterSpacing: '0.06em', color: c.muted }}>{k}</div>
                <div style={{ color: col }}>{v}</div>
              </div>
            ))}
          </div>
        </div>
        <div style={{ background: c.panel, border: `1px solid ${c.line}`, borderRadius: 10, padding: 20 }}>
          <div style={role}>REGELS · KLEUR</div>
          <h3 style={{ fontFamily: f.display, fontSize: 18, fontWeight: 600, color: c.text, margin: '6px 0 12px' }}>Mint = nu. Wit = neutraal.</h3>
          <div style={{ fontSize: 13, lineHeight: 1.6, color: c.muted }}>
            <p style={{ margin: '0 0 10px' }}>Alle vier renderen standaard in <span style={{ color: c.accent }}>{c.accent === '#64f4b3' ? 'mint' : 'accent'}</span> op donker. Voor neutrale momenten (footer-dividers, geprinte WM-4-pagina's) wisselt het naar <span style={{ color: c.text }}>tekst-kleur</span>.</p>
            <p style={{ margin: '0 0 10px' }}>Op <span style={{ color: c.text }}>licht thema</span> blijft het systeem hetzelfde — alleen de accent-tint past zich aan. Tweaks kun je nu rechtsonder wisselen om te checken.</p>
            <p style={{ margin: 0 }}>De warn-kleur is voor het systeem <em>niet</em> toegestaan; T3-brackets in rood zou misleiding zijn.</p>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, {
  WatermarkBg,
  BracketFrame,
  RingMedaillon,
  MonogramStamp,
  LogoSystemReference,
});
