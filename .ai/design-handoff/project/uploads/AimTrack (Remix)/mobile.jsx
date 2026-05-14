// Mobile companion — three screens inside iOS device frames.
// All three are wrapped in IOSDevice (dark, no system nav) so the app
// draws its own AimTrack-branded chrome edge-to-edge.

function MobileScreens({ palette, fonts }) {
  const c = palette;
  const { IOSDevice, AimTrackLogo, Wordmark, Reticle, TargetRings, Spark, Icon, ICONS, SESSIONS } = window;

  // ── shared atoms ────────────────────────────────────────────
  const SP = {
    statusbarH: 54,             // room for iOS status bar + dynamic island
    homeIndicatorH: 34,
  };
  const screenPadTop = SP.statusbarH;

  const tabBar = (active) => (
    <div style={{
      position: 'absolute', left: 16, right: 16, bottom: SP.homeIndicatorH + 6, zIndex: 5,
      padding: 6, borderRadius: 22, background: `${c.panel}d9`, backdropFilter: 'blur(20px)',
      border: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 4,
    }}>
      {[
        { k: 'today',  l: 'Vandaag', i: ICONS.session },
        { k: 'log',    l: 'Loggen',  i: ICONS.add },
        { k: 'coach',  l: 'Coach',   i: ICONS.ai },
        { k: 'meer',   l: 'Meer',    i: ICONS.more },
      ].map(t => (
        <div key={t.k} style={{
          flex: 1, padding: '8px 0', borderRadius: 16,
          background: active === t.k ? `${c.accent}1f` : 'transparent',
          color: active === t.k ? c.accent : c.muted,
          display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 2,
        }}>
          <Icon d={t.i} size={18} sw={active === t.k ? 2 : 1.7} />
          <span style={{ fontSize: 10, fontFamily: fonts.body, fontWeight: 600, letterSpacing: '0.02em' }}>{t.l}</span>
        </div>
      ))}
    </div>
  );

  const screenBase = {
    width: '100%', height: '100%',
    background: c.bg, color: c.text,
    fontFamily: fonts.body, fontSize: 14,
    paddingTop: screenPadTop,
    boxSizing: 'border-box',
    position: 'relative',
    overflow: 'hidden',
  };

  // ── Screen 1: Vandaag ──────────────────────────────────────
  const Today = (
    <div style={screenBase}>
      <div style={{ padding: '8px 20px 0' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <AimTrackLogo size={26} color={c.accent} />
          <div>
            <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.16em' }}>VRIJDAG · 10 MEI</div>
            <div style={{ fontSize: 16, fontWeight: 600 }}>Hoi Marc</div>
          </div>
          <div style={{ marginLeft: 'auto', width: 34, height: 34, borderRadius: 10, background: c.panel, border: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <Icon d={ICONS.bell} size={15} stroke={c.muted} />
          </div>
        </div>
      </div>

      <div style={{ padding: '18px 20px 110px', overflow: 'auto', height: '100%', boxSizing: 'border-box' }}>
        {/* Hero card: this week */}
        <div style={{
          marginTop: 8, padding: 18, borderRadius: 18,
          background: `linear-gradient(160deg, ${c.panel} 0%, ${c.panel2} 100%)`,
          border: `1px solid ${c.line}`,
          position: 'relative', overflow: 'hidden',
        }}>
          <div style={{ position: 'absolute', top: -30, right: -30, opacity: 0.18 }}>
            <Reticle size={180} color={c.accent} stroke={1} dot />
          </div>
          <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.accent, letterSpacing: '0.16em' }}>● DEZE WEEK</div>
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 6, marginTop: 6 }}>
            <div style={{ fontFamily: fonts.mono, fontSize: 56, fontWeight: 600, color: c.text, lineHeight: 1, letterSpacing: '-0.03em' }}>3</div>
            <div style={{ fontSize: 13, color: c.muted, paddingBottom: 8 }}>sessies · 180 schoten</div>
          </div>
          <div style={{ marginTop: 14 }}>
            <Spark data={[538, 542, 540, 545, 547, 549, 552, 547]} w={290} h={36} color={c.accent} strokeW={2} />
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>
            <span>MA</span><span>WO</span><span>VR</span><span>ZO</span>
          </div>
        </div>

        {/* Quick action */}
        <button style={{
          width: '100%', marginTop: 14, padding: '14px 16px',
          borderRadius: 14, border: 'none', background: c.accent, color: c.ctaText,
          fontWeight: 600, fontSize: 15, display: 'flex', alignItems: 'center', gap: 10, cursor: 'pointer',
        }}>
          <Icon d={ICONS.add} size={18} stroke={c.ctaText} sw={2.2} />
          <span style={{ flex: 1, textAlign: 'left' }}>Nieuwe sessie loggen</span>
          <span style={{ fontFamily: fonts.mono, fontSize: 11, opacity: 0.6, letterSpacing: '0.08em' }}>30 SEC</span>
        </button>

        {/* Recent sessions */}
        <div style={{ marginTop: 22, marginBottom: 10, display: 'flex', alignItems: 'center' }}>
          <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', color: c.muted, textTransform: 'uppercase' }}>Recente sessies</div>
          <div style={{ marginLeft: 'auto', fontSize: 12, color: c.accent }}>Alles ›</div>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {SESSIONS.slice(0, 4).map(s => (
            <div key={s.id} style={{ padding: 14, borderRadius: 14, background: c.panel, border: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 12 }}>
              <div style={{ width: 38, height: 38, borderRadius: 10, background: c.panel2, color: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 38px' }}>
                <Icon d={ICONS.target} size={18} sw={1.7} />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 600, color: c.text, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.discipline}</div>
                <div style={{ fontSize: 11, color: c.muted, fontFamily: fonts.mono, marginTop: 2, letterSpacing: '0.02em' }}>{s.date} · {s.weapon}</div>
              </div>
              <div style={{ textAlign: 'right' }}>
                <div style={{ fontFamily: fonts.mono, fontSize: 18, fontWeight: 600, color: c.accent, letterSpacing: '-0.02em' }}>{s.score}</div>
                {s.ai && <div style={{ fontFamily: fonts.mono, fontSize: 8, color: c.accent, letterSpacing: '0.14em', marginTop: 1 }}>● AI</div>}
              </div>
            </div>
          ))}
        </div>

        <div style={{ marginTop: 22, marginBottom: 10, fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', color: c.muted, textTransform: 'uppercase' }}>
          AI-tip · vandaag
        </div>
        <div style={{ padding: 14, borderRadius: 14, background: `${c.accent}10`, border: `1px solid ${c.accent}30` }}>
          <div style={{ display: 'flex', gap: 10, alignItems: 'flex-start' }}>
            <div style={{ width: 28, height: 28, borderRadius: 8, background: `${c.accent}22`, color: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 28px' }}>
              <Icon d={ICONS.ai} size={15} stroke={c.accent} />
            </div>
            <div style={{ fontSize: 13, lineHeight: 1.5, color: c.text }}>
              Vandaag een training? Probeer eens een micro-pauze van 30s na schot 30 — dat was vorige week jouw zwakke punt.
            </div>
          </div>
        </div>
      </div>

      {tabBar('today')}
    </div>
  );

  // ── Screen 2: Live loggen ──────────────────────────────────
  const liveSeries = [10.1, 9.8, 10.3, 9.5, 10.0, 9.9, 9.7, 10.2, 9.6, 9.4, 9.8, 10.1, 9.7, 9.5];
  const liveTotal = liveSeries.reduce((s, v) => s + v, 0);
  const Live = (
    <div style={screenBase}>
      <div style={{ padding: '10px 18px', display: 'flex', alignItems: 'center', gap: 10 }}>
        <div style={{ width: 32, height: 32, borderRadius: 10, background: c.panel, border: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Icon d={{...ICONS.arrow}} size={14} stroke={c.text} sw={2} style={{ transform: 'rotate(180deg)' }} />
        </div>
        <div style={{ flex: 1 }}>
          <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.16em' }}>● LIVE · S-0248</div>
          <div style={{ fontSize: 14, fontWeight: 600 }}>Luchtpistool 10m</div>
        </div>
        <div style={{ padding: '4px 10px', borderRadius: 999, background: `${c.warn}1a`, color: c.warn, fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.12em', fontWeight: 600 }}>
          ● REC 14:18
        </div>
      </div>

      <div style={{ padding: '14px 18px 110px', overflow: 'auto', height: '100%', boxSizing: 'border-box' }}>
        {/* Current score */}
        <div style={{ padding: 18, borderRadius: 18, background: c.panel, border: `1px solid ${c.line}`, textAlign: 'center', position: 'relative', overflow: 'hidden' }}>
          <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', opacity: 0.08 }}>
            <Reticle size={240} color={c.accent} stroke={1} dot />
          </div>
          <div style={{ position: 'relative' }}>
            <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', color: c.muted }}>SCHOT {liveSeries.length + 1} / 60</div>
            <div style={{ fontFamily: fonts.mono, fontSize: 64, fontWeight: 600, color: c.accent, lineHeight: 1, marginTop: 4, letterSpacing: '-0.03em' }}>
              {liveTotal.toFixed(1)}
            </div>
            <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, marginTop: 4, letterSpacing: '0.06em' }}>
              GEM. {(liveTotal / liveSeries.length).toFixed(2)} · BESTE 10.3
            </div>
          </div>
        </div>

        {/* Shot strip */}
        <div style={{ marginTop: 14, padding: 14, borderRadius: 14, background: c.panel, border: `1px solid ${c.line}` }}>
          <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height: 52 }}>
            {liveSeries.map((v, i) => {
              const h = ((v - 8) / 2.5) * 44 + 6;
              return (
                <div key={i} style={{
                  flex: 1, height: h,
                  background: v >= 10 ? c.accent : `${c.muted}55`,
                  borderRadius: 2,
                }} />
              );
            })}
            {/* placeholder for next shots */}
            {Array.from({ length: 60 - liveSeries.length }).slice(0, 20).map((_, i) => (
              <div key={`p${i}`} style={{ flex: 1, height: 6, background: c.line, borderRadius: 2, opacity: 0.5 }} />
            ))}
          </div>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 8, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.12em' }}>
            <span>SCHOT 1</span><span>20</span><span>40</span><span>60</span>
          </div>
        </div>

        {/* Big score numpad */}
        <div style={{ marginTop: 16, fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.18em', marginBottom: 8 }}>VOLGEND SCHOT</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 8 }}>
          {['10.9','10.8','10.7','10.6','10.5','10.4','10.3','10.2','10.1','10.0','9','8'].map((s, i) => {
            const accent = s.startsWith('10');
            return (
              <div key={s} style={{
                padding: '14px 0',
                borderRadius: 12,
                background: accent ? `${c.accent}14` : c.panel,
                border: `1px solid ${accent ? c.accent + '33' : c.line}`,
                color: accent ? c.accent : c.text,
                textAlign: 'center', fontFamily: fonts.mono, fontSize: 16, fontWeight: 600, letterSpacing: '-0.02em',
              }}>
                {s}
              </div>
            );
          })}
        </div>
        <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
          <div style={{ flex: 1, padding: '12px 0', borderRadius: 12, background: c.panel, border: `1px solid ${c.line}`, color: c.muted, textAlign: 'center', fontFamily: fonts.mono, fontSize: 13 }}>
            Decimaal
          </div>
          <div style={{ flex: 1, padding: '12px 0', borderRadius: 12, background: `${c.warn}14`, border: `1px solid ${c.warn}33`, color: c.warn, textAlign: 'center', fontFamily: fonts.mono, fontSize: 13 }}>
            Mis · 0
          </div>
        </div>
      </div>

      {/* Sticky finish button (just above tabbar height area) */}
      <div style={{ position: 'absolute', left: 16, right: 16, bottom: SP.homeIndicatorH + 76, zIndex: 4 }}>
        <button style={{ width: '100%', padding: 14, borderRadius: 14, border: 'none', background: c.text, color: c.bg, fontWeight: 700, fontSize: 14, fontFamily: fonts.body, letterSpacing: '0.02em', cursor: 'pointer' }}>
          Sessie afronden
        </button>
      </div>

      {tabBar('log')}
    </div>
  );

  // ── Screen 3: Coach ────────────────────────────────────────
  const Coach = (
    <div style={screenBase}>
      <div style={{ padding: '10px 18px', display: 'flex', alignItems: 'center', gap: 10, borderBottom: `1px solid ${c.line}` }}>
        <div style={{ width: 36, height: 36, borderRadius: 10, background: c.panel, border: `1px solid ${c.accent}33`, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Icon d={ICONS.ai} size={18} stroke={c.accent} />
        </div>
        <div style={{ flex: 1, minWidth: 0 }}>
          <div style={{ fontSize: 14, fontWeight: 600 }}>AI-coach</div>
          <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.accent, letterSpacing: '0.12em' }}>● ONLINE · S-0247 IN CONTEXT</div>
        </div>
        <Icon d={ICONS.more} size={18} stroke={c.muted} />
      </div>

      <div style={{ padding: '18px 16px 180px', overflow: 'auto', height: '100%', boxSizing: 'border-box', display: 'flex', flexDirection: 'column', gap: 12 }}>
        {/* System pill */}
        <div style={{ alignSelf: 'center', padding: '4px 10px', borderRadius: 999, background: c.panel, border: `1px solid ${c.line}`, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.12em' }}>
          ● VANDAAG · 14:42
        </div>

        {/* User msg */}
        <div style={{ alignSelf: 'flex-end', maxWidth: '82%', padding: '10px 14px', borderRadius: 18, borderTopRightRadius: 6, background: `${c.accent}1f`, color: c.text, fontSize: 14, lineHeight: 1.45, border: `1px solid ${c.accent}33` }}>
          Waarom zakt mijn score altijd rond schot 35?
        </div>

        {/* AI msg */}
        <div style={{ alignSelf: 'flex-start', maxWidth: '85%', padding: '12px 14px', borderRadius: 18, borderTopLeftRadius: 6, background: c.panel, border: `1px solid ${c.line}`, fontSize: 14, lineHeight: 1.5, color: c.text }}>
          In je laatste 6 LP10-sessies daalt het gemiddelde tussen schot 30–40 met <span style={{ color: c.accent, fontFamily: fonts.mono }}>0.3–0.5</span> punt. Tijdstip valt rond minuut 20 — een concentratiedipje.
        </div>

        {/* AI msg with mini chart */}
        <div style={{ alignSelf: 'flex-start', maxWidth: '90%', padding: 12, borderRadius: 18, borderTopLeftRadius: 6, background: c.panel, border: `1px solid ${c.line}` }}>
          <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em', marginBottom: 6 }}>SCORE-DRIFT · SCHOT 30–40</div>
          <Spark data={[9.6,9.5,9.4,9.2,9.0,8.9,9.0,9.1,9.3,9.4]} w={250} h={42} color={c.warn} strokeW={1.8} />
          <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: 4, fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>
            <span>30</span><span>35</span><span>40</span>
          </div>
        </div>

        {/* AI msg with CTA */}
        <div style={{ alignSelf: 'flex-start', maxWidth: '88%', padding: '12px 14px', borderRadius: 18, borderTopLeftRadius: 6, background: c.panel, border: `1px solid ${c.line}`, fontSize: 14, lineHeight: 1.5, color: c.text }}>
          Probeer een micro-pauze van 30s na schot 30. Zal ik dit als trainingsdoel toevoegen?
        </div>
        <div style={{ alignSelf: 'flex-start', display: 'flex', gap: 8 }}>
          <button style={{ padding: '10px 14px', borderRadius: 12, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, fontSize: 13, display: 'flex', alignItems: 'center', gap: 6, cursor: 'pointer' }}>
            <Icon d={ICONS.check} size={13} stroke={c.ctaText} sw={2.2} /> Voeg doel toe
          </button>
          <button style={{ padding: '10px 14px', borderRadius: 12, background: c.panel, border: `1px solid ${c.line}`, color: c.text, fontSize: 13, cursor: 'pointer' }}>
            Later
          </button>
        </div>
      </div>

      {/* Composer */}
      <div style={{ position: 'absolute', left: 0, right: 0, bottom: SP.homeIndicatorH + 70, padding: '10px 16px', background: `linear-gradient(to top, ${c.bg} 80%, ${c.bg}00)`, zIndex: 4 }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 22 }}>
          <Icon d={ICONS.chat} size={15} stroke={c.muted} />
          <span style={{ flex: 1, color: c.muted, fontSize: 13 }}>Stel een vraag…</span>
          <div style={{ width: 28, height: 28, borderRadius: '50%', background: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <Icon d={ICONS.arrow} size={14} stroke={c.ctaText} sw={2.2} />
          </div>
        </div>
      </div>

      {tabBar('coach')}
    </div>
  );

  // ── Layout: 3 phones side by side ──────────────────────────
  const phoneW = 360;
  const phoneH = 780;

  return (
    <div style={{
      width: '100%', height: '100%',
      background: '#f0eee9',
      padding: '32px',
      display: 'flex', gap: 32, alignItems: 'center', justifyContent: 'center',
      boxSizing: 'border-box',
    }}>
      {[['Vandaag', Today], ['Live loggen', Live], ['AI-coach', Coach]].map(([lbl, content], i) => (
        <div key={i} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 14 }}>
          <IOSDevice width={phoneW} height={phoneH} dark>{content}</IOSDevice>
          <div style={{ fontFamily: fonts.mono, fontSize: 11, color: '#444', letterSpacing: '0.14em', textTransform: 'uppercase' }}>
            {lbl}
          </div>
        </div>
      ))}
    </div>
  );
}

window.MobileScreens = MobileScreens;
