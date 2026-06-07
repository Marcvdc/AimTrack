// Four proposals for using the AimTrack logo's visual DNA as a recurring
// graphic element across the product. Each is rendered as a small artboard
// card so the user can compare and pick one.

// ── Reusable: extract the logo's geometry as a standalone SVG -----------
// The logo = circle + N/S/E/W crosshair ticks + AT monogram.

function Reticle({ size = 200, stroke = 1, color = 'currentColor', tickLen = 0.18, gap = 0.06, dot = false, opacity = 1 }) {
  // Pure crosshair (no monogram) — building block for the watermark / frame
  // treatments.
  const cx = size / 2, cy = size / 2, r = size * 0.36;
  const tl = size * tickLen;
  const g  = size * gap;
  return (
    <svg width={size} height={size} viewBox={`0 0 ${size} ${size}`} style={{ display: 'block', opacity }}>
      <circle cx={cx} cy={cy} r={r} fill="none" stroke={color} strokeWidth={stroke} />
      {/* N/S/E/W ticks */}
      <line x1={cx} y1={cy - r - g} x2={cx} y2={cy - r - g - tl} stroke={color} strokeWidth={stroke} />
      <line x1={cx} y1={cy + r + g} x2={cx} y2={cy + r + g + tl} stroke={color} strokeWidth={stroke} />
      <line x1={cx - r - g} y1={cy} x2={cx - r - g - tl} y2={cy} stroke={color} strokeWidth={stroke} />
      <line x1={cx + r + g} y1={cy} x2={cx + r + g + tl} y2={cy} stroke={color} strokeWidth={stroke} />
      {dot && <circle cx={cx} cy={cy} r={Math.max(2, size * 0.012)} fill={color} />}
    </svg>
  );
}

function ATMark({ size = 80, color = 'currentColor', style }) {
  // Simplified AT monogram — interlocked A+T.
  const s = size;
  return (
    <svg width={s} height={s} viewBox="0 0 100 100" style={{ display: 'block', ...style }}>
      <g fill="none" stroke={color} strokeWidth="9" strokeLinecap="square">
        {/* T crossbar */}
        <line x1="22" y1="32" x2="78" y2="32" />
        {/* T stem */}
        <line x1="50" y1="32" x2="50" y2="84" />
        {/* A left leg */}
        <line x1="50" y1="32" x2="30" y2="84" />
        {/* A right leg already overlaps T stem visually */}
        <line x1="50" y1="32" x2="70" y2="84" />
        {/* A crossbar */}
        <line x1="36" y1="62" x2="64" y2="62" />
      </g>
    </svg>
  );
}

// ── Treatment 1: Watermark ----------------------------------------------
// Oversized faint reticle floating behind hero KPIs / section headers.
function LogoT1_Watermark({ palette: c, fonts }) {
  return (
    <div style={{ height: '100%', background: c.bg, color: c.text, fontFamily: fonts.body, padding: 24, position: 'relative', overflow: 'hidden' }}>
      <div style={{ position: 'absolute', top: -40, right: -60, opacity: 1 }}>
        <Reticle size={360} color={c.accent} stroke={1} opacity={0.10} dot />
      </div>
      <div style={{ position: 'relative', zIndex: 1 }}>
        <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', textTransform: 'uppercase', color: c.muted }}>Sessies / mei</div>
        <div style={{ fontFamily: fonts.mono, fontSize: 72, fontWeight: 600, color: c.text, lineHeight: 1, marginTop: 8, letterSpacing: '-0.03em' }}>
          14
          <span style={{ fontSize: 18, color: c.accent, marginLeft: 10, fontWeight: 600 }}>▲ +3</span>
        </div>
        <div style={{ fontSize: 13, color: c.muted, marginTop: 8 }}>2.920 schoten · 11 met AI-reflectie</div>
        <div style={{ position: 'absolute', bottom: -8, left: 0, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.18em' }}>T1 · RETICLE WATERMARK</div>
      </div>
    </div>
  );
}

// ── Treatment 2: Ring-as-frame ------------------------------------------
// The logo's circle becomes the actual frame around hero stats — brand =
// functional UI.
function LogoT2_RingFrame({ palette: c, fonts }) {
  const size = 220;
  return (
    <div style={{ height: '100%', background: c.bg, color: c.text, fontFamily: fonts.body, padding: 24, display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative' }}>
      <div style={{ position: 'relative', width: size, height: size }}>
        <Reticle size={size} color={c.accent} stroke={1.5} dot={false} opacity={0.85} />
        <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.18em', color: c.muted }}>SCORE</div>
          <div style={{ fontFamily: fonts.mono, fontSize: 44, fontWeight: 600, color: c.accent, lineHeight: 1, letterSpacing: '-0.02em' }}>547</div>
          <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, marginTop: 4 }}>/600</div>
        </div>
      </div>
      <div style={{ position: 'absolute', bottom: 16, left: 24, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.18em' }}>T2 · RING-AS-FRAME</div>
    </div>
  );
}

// ── Treatment 3: Corner brackets from crosshair ticks --------------------
// The N/S/E/W ticks shrink into corner brackets framing cards and modules.
function LogoT3_Brackets({ palette: c, fonts }) {
  const corner = (x, y) => ({
    position: 'absolute',
    [x]: -1, [y]: -1,
    width: 18, height: 18,
    borderTop: y === 'top' ? `1.5px solid ${c.accent}` : 'none',
    borderBottom: y === 'bottom' ? `1.5px solid ${c.accent}` : 'none',
    borderLeft: x === 'left' ? `1.5px solid ${c.accent}` : 'none',
    borderRight: x === 'right' ? `1.5px solid ${c.accent}` : 'none',
  });
  return (
    <div style={{ height: '100%', background: c.bg, padding: 24, color: c.text, fontFamily: fonts.body, display: 'flex', alignItems: 'center', justifyContent: 'center', position: 'relative' }}>
      <div style={{ position: 'relative', padding: 18, border: `1px solid ${c.line}`, background: c.panel, width: 240 }}>
        <div style={corner('left', 'top')} />
        <div style={corner('right', 'top')} />
        <div style={corner('left', 'bottom')} />
        <div style={corner('right', 'bottom')} />
        <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.18em', color: c.accent }}>● AI-REFLECTIE</div>
        <div style={{ fontSize: 13, lineHeight: 1.5, color: c.text, marginTop: 8 }}>
          Sterke openingsserie. Vanaf schot 35 daalt gem. met <span style={{ color: c.accent, fontFamily: fonts.mono }}>−0.4</span>.
        </div>
      </div>
      <div style={{ position: 'absolute', bottom: 16, left: 24, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.18em' }}>T3 · CROSSHAIR BRACKETS</div>
    </div>
  );
}

// ── Treatment 4: Monogram stamp -----------------------------------------
// Bare AT monogram as a small "stamp" on card corners, section dividers,
// and chart annotations. The reticle steps back, the letterforms come fwd.
function LogoT4_Stamp({ palette: c, fonts }) {
  return (
    <div style={{ height: '100%', background: c.bg, padding: 24, color: c.text, fontFamily: fonts.body, position: 'relative' }}>
      <div style={{ position: 'relative', padding: '16px 18px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8 }}>
        <div style={{ position: 'absolute', top: -12, right: 16, padding: '4px 8px', background: c.accent, color: c.ctaText, display: 'flex', alignItems: 'center', gap: 6, borderRadius: 4 }}>
          <ATMark size={14} color={c.ctaText} />
          <span style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.16em', fontWeight: 700 }}>VERIFIED</span>
        </div>
        <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', color: c.muted }}>SESSIE S-0247</div>
        <div style={{ fontSize: 16, fontWeight: 600, color: c.text, marginTop: 6 }}>Luchtpistool · 547 / 600</div>
        <div style={{ fontSize: 12, color: c.muted, marginTop: 4 }}>WM-4 conform · klaar voor export</div>
      </div>
      <div style={{ display: 'flex', gap: 12, marginTop: 18, alignItems: 'center' }}>
        <ATMark size={22} color={c.accent} />
        <div style={{ flex: 1, height: 1, background: c.line }} />
        <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.18em' }}>MEI 2026</div>
        <div style={{ flex: 1, height: 1, background: c.line }} />
        <ATMark size={22} color={c.muted} />
      </div>
      <div style={{ position: 'absolute', bottom: 16, left: 24, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.18em' }}>T4 · MONOGRAM STAMP</div>
    </div>
  );
}

Object.assign(window, { Reticle, ATMark, LogoT1_Watermark, LogoT2_RingFrame, LogoT3_Brackets, LogoT4_Stamp });
