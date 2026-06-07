from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class TargetSpec:
    """Specification for a single KNSA/ISSF shooting target type."""

    name: str
    distance_m: int
    ring_diameters_mm: dict[int, float]
    """Ring number (1–10) → outer-edge diameter in mm."""
    black_area_diameter_mm: float
    """Diameter of the black aiming-mark area in mm."""
    bullet_diameter_mm: float
    """Nominal bullet diameter in mm (largest caliber for this discipline)."""

    @property
    def ring1_diameter_mm(self) -> float:
        return self.ring_diameters_mm[1]

    @property
    def ring10_diameter_mm(self) -> float:
        return self.ring_diameters_mm[10]

    @property
    def black_to_total_ratio(self) -> float:
        """Ratio of black area to full target diameter (used for scaling)."""
        return self.black_area_diameter_mm / self.ring1_diameter_mm

    @property
    def total_to_black_scale(self) -> float:
        """Scale factor: black-area radius → full-target radius."""
        return 1.0 / self.black_to_total_ratio

    def ring_diameter_mm(self, ring: int) -> float:
        if ring not in self.ring_diameters_mm:
            raise ValueError(f"Ring {ring} bestaat niet in spec '{self.name}'")
        return self.ring_diameters_mm[ring]


def _pistol_25m_rings() -> dict[int, float]:
    """ISSF 25m Standard/Centre-fire Pistol target — 50mm per ring."""
    return {ring: 50.0 * (11 - ring) for ring in range(1, 11)}


def _kkG_50m_rings() -> dict[int, float]:
    """ISSF 50m Rifle target — 8mm radius (16mm diameter) per ring."""
    base_radius_mm = 5.2  # ring-10 radius
    step_mm = 8.0  # radius increment per ring
    return {
        ring: round(2.0 * (base_radius_mm + step_mm * (10 - ring)), 2)
        for ring in range(1, 11)
    }


def _woerden_rings() -> dict[int, float]:
    """KNSA Woerden target (100m KKG & GKG) — 33.33mm per ring."""
    ring10_mm = 28.0
    step_mm = 300.0 / 9.0  # (328 - 28) / 9 rings
    return {
        ring: round(ring10_mm + step_mm * (10 - ring), 2)
        for ring in range(1, 11)
    }


# ---------------------------------------------------------------------------
# Canonical target specifications
# ---------------------------------------------------------------------------

KKP_25M = TargetSpec(
    name="25m KKP",
    distance_m=25,
    ring_diameters_mm=_pistol_25m_rings(),
    black_area_diameter_mm=200.0,  # rings 7–10
    bullet_diameter_mm=5.6,        # .22 LR
)

GKP_25M = TargetSpec(
    name="25m GKP",
    distance_m=25,
    ring_diameters_mm=_pistol_25m_rings(),
    black_area_diameter_mm=200.0,  # rings 7–10
    bullet_diameter_mm=9.0,        # 9 mm
)

KKG_50M = TargetSpec(
    name="50m KKG",
    distance_m=50,
    ring_diameters_mm=_kkG_50m_rings(),
    black_area_diameter_mm=112.4,  # rings 3–10
    bullet_diameter_mm=5.6,        # .22 LR
)

KKG_100M = TargetSpec(
    name="100m KKG",
    distance_m=100,
    ring_diameters_mm=_woerden_rings(),
    black_area_diameter_mm=195.0,  # rings 5–10
    bullet_diameter_mm=5.6,        # .22 LR
)

GKG_100M = TargetSpec(
    name="100m GKG",
    distance_m=100,
    ring_diameters_mm=_woerden_rings(),
    black_area_diameter_mm=195.0,  # rings 5–10
    bullet_diameter_mm=7.62,       # .308 Win (largest caliber in discipline)
)

TARGET_SPECS: dict[str, TargetSpec] = {
    "kkp_25m": KKP_25M,
    "gkp_25m": GKP_25M,
    "kkg_50m": KKG_50M,
    "kkg_100m": KKG_100M,
    "gkg_100m": GKG_100M,
}
