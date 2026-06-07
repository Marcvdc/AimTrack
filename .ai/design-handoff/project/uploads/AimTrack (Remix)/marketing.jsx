// AimTrack — marketing landing page (single long-scroll page).
// Reuses the brand vocabulary: mint accent, dark bg, mono numerals,
// reticle as recurring graphic element.

function MarketingLanding({ palette, fonts }) {
  const c = palette;
  const { Wordmark, AimTrackLogo, TargetRings, Spark, Reticle, ATMark, Icon, ICONS, SESSIONS } = window;

  const featurePill = {
    display: 'inline-flex', alignItems: 'center', gap: 6,
    padding: '5px 10px', border: `1px solid ${c.line}`, borderRadius: 999,
    fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.04em',
  };

  const sectionLabel = {
    fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.22em',
    textTransform: 'uppercase', color: c.accent,
    display: 'inline-flex', alignItems: 'center', gap: 8,
  };

  const dotMark = (
    <span style={{ width: 5, height: 5, borderRadius: '50%', background: c.accent, display: 'inline-block' }} />
  );

  return (
    <div style={{
      width: '100%', height: '100%',
      background: `linear-gradient(to bottom, ${c.bg}, ${c.panel} 60%, ${c.bg})`,
      color: c.text, fontFamily: fonts.body,
      overflow: 'auto', position: 'relative',
    }}>
      {/* Subtle reticle in top-right */}
      <div style={{ position: 'absolute', top: 40, right: 60, pointerEvents: 'none' }}>
        <Reticle size={420} color={c.accent} stroke={1} opacity={0.07} dot />
      </div>

      {/* ── Nav ─────────────────────────────────────────────────── */}
      <nav style={{
        position: 'sticky', top: 0, zIndex: 10,
        display: 'flex', alignItems: 'center', gap: 28,
        padding: '18px 64px',
        background: `${c.bg}cc`, backdropFilter: 'blur(12px)',
        borderBottom: `1px solid ${c.line}`,
      }}>
        <Wordmark size={26} color={c.text} accent={c.accent} />
        <div style={{ display: 'flex', gap: 24, marginLeft: 24, fontSize: 13, color: c.muted }}>
          <span style={{ color: c.text }}>Functies</span>
          <span>AI-coach</span>
          <span>Self-hosted</span>
          <span>Prijzen</span>
          <span>Github</span>
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 10, alignItems: 'center' }}>
          <span style={{ fontSize: 13, color: c.muted }}>Inloggen</span>
          <button style={{ padding: '8px 14px', borderRadius: 8, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, fontSize: 13, cursor: 'pointer' }}>
            Probeer gratis
          </button>
        </div>
      </nav>

      {/* ── Hero ────────────────────────────────────────────────── */}
      <section style={{ padding: '88px 64px 64px', display: 'grid', gridTemplateColumns: '1.1fr 1fr', gap: 60, alignItems: 'center', position: 'relative' }}>
        <div>
          <div style={{ ...sectionLabel, marginBottom: 18 }}>
            {dotMark} VOOR SPORTSCHUTTERS · NL
          </div>
          <h1 style={{
            fontFamily: fonts.display, fontSize: 64, fontWeight: 600,
            letterSpacing: '-0.03em', lineHeight: 1.02, margin: 0, color: c.text,
          }}>
            Je schietsessies,<br/>
            <span style={{ color: c.accent }}>scherp in beeld.</span>
          </h1>
          <p style={{ fontSize: 18, lineHeight: 1.55, color: c.muted, marginTop: 22, maxWidth: 520 }}>
            AimTrack logt je trainingen, herkent patronen in je groepering en
            geeft per sessie een AI-reflectie. Self-hosted, WM-4 conform,
            zonder gedoe.
          </p>
          <div style={{ display: 'flex', gap: 12, marginTop: 32 }}>
            <button style={{ padding: '14px 22px', borderRadius: 10, border: 'none', background: c.accent, color: c.ctaText, fontWeight: 600, fontSize: 15, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8 }}>
              30 dagen gratis proberen <Icon d={ICONS.arrow} size={14} stroke={c.ctaText} sw={2} />
            </button>
            <button style={{ padding: '14px 22px', borderRadius: 10, border: `1px solid ${c.line}`, background: 'transparent', color: c.text, fontSize: 15, cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 8 }}>
              <Icon d={ICONS.shield} size={14} /> Bekijk op GitHub
            </button>
          </div>
          <div style={{ display: 'flex', gap: 10, marginTop: 24, flexWrap: 'wrap' }}>
            <span style={featurePill}>● Self-hosted</span>
            <span style={featurePill}>● WM-4 export</span>
            <span style={featurePill}>● AI-reflectie</span>
            <span style={featurePill}>● Open-source</span>
          </div>
        </div>

        {/* Hero visual: a reticle-framed score readout */}
        <div style={{ position: 'relative', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <div style={{ position: 'relative', width: 420, height: 420 }}>
            <Reticle size={420} color={c.accent} stroke={1.5} opacity={0.85} />
            {/* Center hit pattern */}
            <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
              <TargetRings size={220} accent={c.accent} dim={c.text} ringStroke={1} />
            </div>
            {/* Floating callouts */}
            <div style={{
              position: 'absolute', top: 12, left: -36,
              padding: '8px 12px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8,
              boxShadow: `0 8px 24px ${c.bg}80`,
            }}>
              <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.16em', color: c.muted }}>SCORE</div>
              <div style={{ fontFamily: fonts.mono, fontSize: 22, color: c.accent, fontWeight: 600 }}>547</div>
            </div>
            <div style={{
              position: 'absolute', bottom: 16, right: -28,
              padding: '8px 12px', background: c.panel, border: `1px solid ${c.line}`, borderRadius: 8,
            }}>
              <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.16em', color: c.muted }}>GROEP</div>
              <div style={{ fontFamily: fonts.mono, fontSize: 22, color: c.text, fontWeight: 600 }}>22<span style={{ fontSize: 12, color: c.muted, marginLeft: 4 }}>mm</span></div>
            </div>
            <div style={{
              position: 'absolute', top: '52%', right: -56,
              padding: '8px 12px', background: `${c.accent}14`, border: `1px solid ${c.accent}40`, borderRadius: 8,
            }}>
              <div style={{ fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.16em', color: c.accent }}>● AI</div>
              <div style={{ fontSize: 11, color: c.text, marginTop: 2 }}>Sterke opening,<br/>dip schot 35</div>
            </div>
          </div>
        </div>
      </section>

      {/* ── Trust strip ─────────────────────────────────────────── */}
      <section style={{ padding: '24px 64px', borderTop: `1px solid ${c.line}`, borderBottom: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 40 }}>
        <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.18em', color: c.muted, textTransform: 'uppercase' }}>
          Gebruikt door sportschutters bij
        </div>
        <div style={{ display: 'flex', gap: 40, flex: 1, alignItems: 'center', color: c.muted, fontFamily: fonts.display, fontSize: 16, opacity: 0.65 }}>
          <span>SV Diemen</span>
          <span>Schuttersgilde Hilversum</span>
          <span>KNSA · regio West</span>
          <span>SV De Adelaar</span>
          <span>Pistoolclub Utrecht</span>
        </div>
      </section>

      {/* ── Features ────────────────────────────────────────────── */}
      <section style={{ padding: '88px 64px', position: 'relative' }}>
        <div style={sectionLabel}>{dotMark} WAT JE KRIJGT</div>
        <h2 style={{ fontFamily: fonts.display, fontSize: 44, fontWeight: 600, letterSpacing: '-0.02em', margin: '12px 0 0', color: c.text, maxWidth: 720 }}>
          Een logboek dat <span style={{ color: c.accent }}>meedenkt</span>, niet alleen onthoudt.
        </h2>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, marginTop: 44 }}>
          {[
            {
              icon: ICONS.session,
              kicker: '01 · SESSIES',
              title: 'Log één sessie in 30 seconden',
              body: 'Discipline, wapen, baan en score in een paar tikken. Notities en foto\'s desgewenst. Daarna gaat AimTrack ermee aan de slag.',
              demo: (
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontFamily: fonts.mono, fontSize: 11 }}>
                  {SESSIONS.slice(0, 3).map(s => (
                    <div key={s.id} style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '6px 10px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6 }}>
                      <span style={{ color: c.muted }}>{s.date}</span>
                      <span style={{ flex: 1, color: c.text, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.discipline}</span>
                      <span style={{ color: c.accent, fontWeight: 600 }}>{s.score}</span>
                    </div>
                  ))}
                </div>
              )
            },
            {
              icon: ICONS.ai,
              kicker: '02 · AI-COACH',
              title: 'Reflectie per sessie, zonder typen',
              body: 'Sterke punten, verbeterpunten, en concrete oefenadviezen. Vraag follow-ups in normaal Nederlands.',
              demo: (
                <div style={{ padding: 10, background: `${c.accent}10`, border: `1px solid ${c.accent}33`, borderRadius: 8, fontSize: 12, color: c.text, lineHeight: 1.5 }}>
                  <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.accent, letterSpacing: '0.14em', marginBottom: 6 }}>● AI · S-0247</div>
                  "Vanaf schot 35 zakt het gemiddelde 0.4 — concentratiedipje rond minuut 22."
                </div>
              )
            },
            {
              icon: ICONS.spark,
              kicker: '03 · TRENDS',
              title: 'Zie progressie per wapen, niet per gevoel',
              body: 'Score-trend, groepering, schot-voor-schot — voor elk wapen apart. Vergelijk maanden of disciplines naast elkaar.',
              demo: (
                <div style={{ padding: 10, background: c.bg, border: `1px solid ${c.line}`, borderRadius: 8 }}>
                  <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>LP500 · 30D</div>
                  <Spark w={250} h={50} color={c.accent} strokeW={1.8} />
                </div>
              )
            },
            {
              icon: ICONS.export,
              kicker: '04 · WM-4',
              title: 'Wet-conforme administratie',
              body: 'Genereer een WM-4 register-export voor je vereniging. Compleet, gefilterd, en klaar voor inlevering.',
              demo: (
                <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '10px 12px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 8 }}>
                  <ATMark size={20} color={c.accent} />
                  <div style={{ flex: 1 }}>
                    <div style={{ fontSize: 12, fontWeight: 600 }}>WM-4 · mei 2026</div>
                    <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.08em' }}>247 SESSIES · GEREED</div>
                  </div>
                  <Icon d={ICONS.export} size={16} stroke={c.accent} />
                </div>
              )
            },
            {
              icon: ICONS.shield,
              kicker: '05 · PRIVACY',
              title: 'Self-hosted of bij ons — jij kiest',
              body: 'Draai AimTrack op je eigen server (Docker, 5 min setup) of gebruik onze NL-cloud. Je data is van jou, altijd.',
              demo: (
                <div style={{ display: 'flex', gap: 8 }}>
                  <div style={{ flex: 1, padding: '10px 12px', background: c.bg, border: `1px solid ${c.accent}40`, borderRadius: 8 }}>
                    <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.accent, letterSpacing: '0.14em' }}>● ACTIEF</div>
                    <div style={{ fontSize: 12, fontWeight: 600, marginTop: 4 }}>Self-hosted</div>
                  </div>
                  <div style={{ flex: 1, padding: '10px 12px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 8 }}>
                    <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em' }}>OFF</div>
                    <div style={{ fontSize: 12, fontWeight: 600, marginTop: 4 }}>NL-cloud</div>
                  </div>
                </div>
              )
            },
            {
              icon: ICONS.weapon,
              kicker: '06 · WAPENS',
              title: 'Eén overzicht per wapen',
              body: 'Schotaantal, onderhoud, kalibratie, gem. score. Alles wat je nodig hebt voor de keuringsbrief en je eigen ritueel.',
              demo: (
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 6 }}>
                  {['Walther LP500', 'CZ Shadow 2', 'Pardini K22'].slice(0, 4).map((n, i) => (
                    <div key={i} style={{ padding: '8px 10px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 6, fontSize: 11 }}>
                      <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.12em' }}>W-00{i + 1}</div>
                      <div style={{ color: c.text, fontWeight: 600, marginTop: 2 }}>{n}</div>
                    </div>
                  ))}
                </div>
              )
            },
          ].map((f, i) => (
            <div key={i} style={{
              padding: 24, borderRadius: 14,
              background: c.panel, border: `1px solid ${c.line}`,
              display: 'flex', flexDirection: 'column', gap: 14,
              position: 'relative', overflow: 'hidden',
            }}>
              <div style={{ position: 'absolute', top: -1, right: -1, width: 18, height: 18, borderTop: `1.5px solid ${c.accent}`, borderRight: `1.5px solid ${c.accent}` }} />
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Icon d={f.icon} size={18} stroke={c.accent} />
                <div style={{ fontFamily: fonts.mono, fontSize: 10, color: c.muted, letterSpacing: '0.18em' }}>{f.kicker}</div>
              </div>
              <h3 style={{ fontFamily: fonts.display, fontSize: 22, fontWeight: 600, letterSpacing: '-0.015em', margin: 0, color: c.text, lineHeight: 1.2 }}>{f.title}</h3>
              <p style={{ fontSize: 14, lineHeight: 1.55, color: c.muted, margin: 0 }}>{f.body}</p>
              <div style={{ marginTop: 'auto', paddingTop: 10 }}>{f.demo}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ── AI-coach deep dive ─────────────────────────────────── */}
      <section style={{ padding: '96px 64px', borderTop: `1px solid ${c.line}`, position: 'relative' }}>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.1fr', gap: 64, alignItems: 'center' }}>
          <div>
            <div style={sectionLabel}>{dotMark} AI-COACH</div>
            <h2 style={{ fontFamily: fonts.display, fontSize: 44, fontWeight: 600, letterSpacing: '-0.02em', margin: '12px 0 16px', color: c.text }}>
              Een coach die <br/><span style={{ color: c.accent }}>jouw cijfers</span> leest.
            </h2>
            <p style={{ fontSize: 16, lineHeight: 1.6, color: c.muted, maxWidth: 480 }}>
              AimTrack analyseert je sessies in context: welke serie zakt, hoe je
              ademritme zich verhoudt tot je groepering, wat goed werkt met
              welk wapen. Stel vragen, krijg antwoorden onderbouwd met je
              eigen data.
            </p>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 28 }}>
              {[
                'Per-sessie reflectie zonder dat je iets hoeft te typen',
                'Trainingsdoelen automatisch voorgesteld, jij kiest',
                'Vergelijk wapens, disciplines, of periodes naast elkaar',
                'Alles draait lokaal — je data verlaat de server niet',
              ].map((t, i) => (
                <div key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: 10 }}>
                  <div style={{ width: 20, height: 20, borderRadius: '50%', background: `${c.accent}1f`, color: c.accent, display: 'flex', alignItems: 'center', justifyContent: 'center', flex: '0 0 20px', marginTop: 1 }}>
                    <Icon d={ICONS.check} size={12} stroke={c.accent} sw={2.2} />
                  </div>
                  <span style={{ fontSize: 14, color: c.text }}>{t}</span>
                </div>
              ))}
            </div>
          </div>

          {/* Mock chat */}
          <div style={{ background: c.panel, border: `1px solid ${c.line}`, borderRadius: 16, padding: 24, display: 'flex', flexDirection: 'column', gap: 14, position: 'relative', overflow: 'hidden' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 10, paddingBottom: 14, borderBottom: `1px solid ${c.line}` }}>
              <div style={{ width: 8, height: 8, borderRadius: '50%', background: c.accent }} />
              <div style={{ fontFamily: fonts.mono, fontSize: 10, letterSpacing: '0.14em', color: c.muted }}>AI-COACH · LIVE</div>
              <div style={{ marginLeft: 'auto', fontFamily: fonts.mono, fontSize: 10, color: c.muted }}>S-0247 · LP500</div>
            </div>
            <div style={{ alignSelf: 'flex-end', maxWidth: '85%', padding: '10px 14px', background: `${c.accent}14`, border: `1px solid ${c.accent}33`, borderRadius: 12, fontSize: 13, color: c.text }}>
              Waarom zakt mijn score rond schot 35?
            </div>
            <div style={{ alignSelf: 'flex-start', maxWidth: '90%', padding: '12px 14px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 12, fontSize: 13, color: c.text, lineHeight: 1.55 }}>
              In je laatste 6 LP10-sessies daalt het gemiddelde tussen schot 30–40 met <span style={{ color: c.accent, fontFamily: fonts.mono }}>0.3–0.5</span>. Het tijdstip valt rond minuut 20 van je sessie — een typisch concentratiedipje.
            </div>
            <div style={{ alignSelf: 'flex-start', width: '90%', padding: 12, background: c.bg, border: `1px solid ${c.line}`, borderRadius: 12 }}>
              <div style={{ fontFamily: fonts.mono, fontSize: 9, color: c.muted, letterSpacing: '0.14em', marginBottom: 6 }}>SCORE-DRIFT · SCHOT 30–40</div>
              <Spark data={[9.6,9.5,9.4,9.2,9.0,8.9,9.0,9.1,9.3,9.4,9.4]} w={380} h={50} color={c.warn} strokeW={1.8} />
            </div>
            <div style={{ alignSelf: 'flex-end', maxWidth: '85%', padding: '10px 14px', background: `${c.accent}14`, border: `1px solid ${c.accent}33`, borderRadius: 12, fontSize: 13, color: c.text }}>
              Wat raad je aan?
            </div>
            <div style={{ alignSelf: 'flex-start', maxWidth: '90%', padding: '12px 14px', background: c.bg, border: `1px solid ${c.line}`, borderRadius: 12, fontSize: 13, color: c.text, lineHeight: 1.55 }}>
              Micro-pauze van 30s na schot 30, cadans rond 30s aanhouden, en 2× 10 min droogoefenen per week. Zal ik dit als doel voor mei toevoegen?
            </div>
          </div>
        </div>
      </section>

      {/* ── Self-hosted CTA strip ──────────────────────────────── */}
      <section style={{ padding: '64px 64px', borderTop: `1px solid ${c.line}`, borderBottom: `1px solid ${c.line}`, background: c.bg, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 48, alignItems: 'center' }}>
        <div>
          <div style={sectionLabel}>{dotMark} SELF-HOSTED · OPEN-SOURCE</div>
          <h2 style={{ fontFamily: fonts.display, fontSize: 38, fontWeight: 600, letterSpacing: '-0.02em', margin: '10px 0 14px' }}>
            Eén command, eigen instance.
          </h2>
          <p style={{ fontSize: 15, color: c.muted, lineHeight: 1.6, maxWidth: 480 }}>
            Docker compose up. Klaar. AimTrack is open-source onder MIT-licentie,
            werkt op een Raspberry Pi, en synchroniseert nergens heen.
          </p>
        </div>
        <div style={{ padding: 20, background: c.panel, border: `1px solid ${c.line}`, borderRadius: 12, fontFamily: fonts.mono, fontSize: 13, color: c.text }}>
          <div style={{ display: 'flex', gap: 6, marginBottom: 14 }}>
            <div style={{ width: 10, height: 10, borderRadius: '50%', background: c.warn + '88' }} />
            <div style={{ width: 10, height: 10, borderRadius: '50%', background: c.accent + '88' }} />
            <div style={{ width: 10, height: 10, borderRadius: '50%', background: c.muted + '55' }} />
            <span style={{ marginLeft: 'auto', fontSize: 10, color: c.muted, letterSpacing: '0.12em' }}>~/aimtrack</span>
          </div>
          <div style={{ color: c.muted }}><span style={{ color: c.accent }}>$</span> git clone github.com/marcvdc/AimTrack</div>
          <div style={{ color: c.muted }}><span style={{ color: c.accent }}>$</span> cd AimTrack && docker compose up -d</div>
          <div style={{ color: c.muted, marginTop: 6, fontSize: 11 }}>✓ AimTrack draait op <span style={{ color: c.accent }}>http://localhost:8000</span></div>
          <div style={{ color: c.muted, fontSize: 11 }}>✓ Klaar voor je eerste sessie.</div>
        </div>
      </section>

      {/* ── Pricing teaser ─────────────────────────────────────── */}
      <section style={{ padding: '88px 64px' }}>
        <div style={{ textAlign: 'center', marginBottom: 40 }}>
          <div style={sectionLabel}>{dotMark} PRIJZEN</div>
          <h2 style={{ fontFamily: fonts.display, fontSize: 40, fontWeight: 600, letterSpacing: '-0.02em', margin: '10px 0 8px' }}>
            Eerlijk, en voor altijd.
          </h2>
          <p style={{ fontSize: 14, color: c.muted, maxWidth: 520, margin: '0 auto' }}>
            Zelf hosten is gratis. Hosting + AI bij ons start bij € 4 / maand.
          </p>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16, maxWidth: 1100, margin: '0 auto' }}>
          {[
            { name: 'Self-hosted', price: '€ 0', sub: 'voor altijd', features: ['Volledige app', 'Onbeperkt sessies', 'WM-4 export', 'Eigen AI-model (optioneel)'], cta: 'Bekijk op GitHub', primary: false },
            { name: 'Schutter', price: '€ 4', sub: '/ maand', features: ['Hosted bij AimTrack NL', 'Onbeperkt sessies', 'AI-coach inbegrepen', 'WM-4 export'], cta: 'Probeer 30 dagen', primary: true },
            { name: 'Vereniging', price: '€ 1', sub: '/ lid / maand', features: ['Beheer voor bestuur', 'WM-4 batch-export', 'SSO via KNSA', 'Prioriteits-support'], cta: 'Vraag offerte', primary: false },
          ].map((p, i) => (
            <div key={i} style={{
              padding: 28, borderRadius: 14,
              background: p.primary ? `linear-gradient(180deg, ${c.accent}12, ${c.panel})` : c.panel,
              border: `1px solid ${p.primary ? c.accent + '60' : c.line}`,
              position: 'relative',
              display: 'flex', flexDirection: 'column', gap: 16,
            }}>
              {p.primary && (
                <div style={{ position: 'absolute', top: -10, left: 24, padding: '3px 10px', background: c.accent, color: c.ctaText, borderRadius: 4, fontFamily: fonts.mono, fontSize: 9, letterSpacing: '0.16em', fontWeight: 700 }}>POPULAIR</div>
              )}
              <div>
                <div style={{ fontFamily: fonts.mono, fontSize: 11, letterSpacing: '0.18em', textTransform: 'uppercase', color: p.primary ? c.accent : c.muted }}>{p.name}</div>
                <div style={{ marginTop: 8, display: 'flex', alignItems: 'baseline', gap: 6 }}>
                  <span style={{ fontFamily: fonts.display, fontSize: 44, fontWeight: 600, color: c.text, letterSpacing: '-0.02em' }}>{p.price}</span>
                  <span style={{ fontSize: 13, color: c.muted }}>{p.sub}</span>
                </div>
              </div>
              <div style={{ height: 1, background: c.line }} />
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                {p.features.map(f => (
                  <div key={f} style={{ display: 'flex', gap: 10, alignItems: 'center', fontSize: 13, color: c.text }}>
                    <Icon d={ICONS.check} size={13} stroke={c.accent} sw={2} /> {f}
                  </div>
                ))}
              </div>
              <button style={{
                marginTop: 'auto',
                padding: '11px 14px', borderRadius: 8,
                border: p.primary ? 'none' : `1px solid ${c.line}`,
                background: p.primary ? c.accent : 'transparent',
                color: p.primary ? c.ctaText : c.text,
                fontWeight: 600, fontSize: 13, cursor: 'pointer',
              }}>
                {p.cta}
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* ── Footer ─────────────────────────────────────────────── */}
      <footer style={{ padding: '40px 64px', borderTop: `1px solid ${c.line}`, display: 'flex', alignItems: 'center', gap: 16 }}>
        <Wordmark size={22} color={c.muted} accent={c.accent} />
        <div style={{ fontFamily: fonts.mono, fontSize: 11, color: c.muted, letterSpacing: '0.08em' }}>v3.2 · MIT · NL</div>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 22, fontSize: 12, color: c.muted }}>
          <span>Docs</span><span>Changelog</span><span>GitHub</span><span>Contact</span>
        </div>
      </footer>
    </div>
  );
}

window.MarketingLanding = MarketingLanding;
