"""Tests for TargetSpec configuration."""
from __future__ import annotations

import pytest

from app.config import (
    GKG_100M,
    GKP_25M,
    KKG_100M,
    KKG_50M,
    KKP_25M,
    TARGET_SPECS,
    TargetSpec,
)


class TestTargetSpecRingDiameters:
    def test_kkp_25m_ring10(self) -> None:
        assert KKP_25M.ring_diameters_mm[10] == pytest.approx(50.0)

    def test_kkp_25m_ring1(self) -> None:
        assert KKP_25M.ring_diameters_mm[1] == pytest.approx(500.0)

    def test_kkp_25m_uniform_spacing(self) -> None:
        """Each ring should add exactly 50mm in diameter."""
        for ring in range(1, 10):
            diff = KKP_25M.ring_diameters_mm[ring] - KKP_25M.ring_diameters_mm[ring + 1]
            assert diff == pytest.approx(50.0, abs=0.1), f"Ring {ring} spacing wrong"

    def test_gkp_same_dimensions_as_kkp(self) -> None:
        """GKP and KKP use the same ISSF target dimensions."""
        for ring in range(1, 11):
            assert GKP_25M.ring_diameters_mm[ring] == pytest.approx(
                KKP_25M.ring_diameters_mm[ring]
            )

    def test_gkp_larger_bullet(self) -> None:
        assert GKP_25M.bullet_diameter_mm > KKP_25M.bullet_diameter_mm

    def test_kkg_50m_ring10(self) -> None:
        assert KKG_50M.ring_diameters_mm[10] == pytest.approx(10.4, abs=0.1)

    def test_kkg_50m_ring1(self) -> None:
        assert KKG_50M.ring_diameters_mm[1] == pytest.approx(154.4, abs=0.2)

    def test_kkg_50m_uniform_spacing(self) -> None:
        """Each ring should add ~16mm in diameter (8mm radius step)."""
        for ring in range(1, 10):
            diff = KKG_50M.ring_diameters_mm[ring] - KKG_50M.ring_diameters_mm[ring + 1]
            assert diff == pytest.approx(16.0, abs=0.1), f"Ring {ring} spacing wrong"

    def test_woerden_ring10(self) -> None:
        assert KKG_100M.ring_diameters_mm[10] == pytest.approx(28.0, abs=0.1)
        assert GKG_100M.ring_diameters_mm[10] == pytest.approx(28.0, abs=0.1)

    def test_woerden_ring1(self) -> None:
        assert KKG_100M.ring_diameters_mm[1] == pytest.approx(328.0, abs=0.1)
        assert GKG_100M.ring_diameters_mm[1] == pytest.approx(328.0, abs=0.1)

    def test_woerden_black_area_matches_ring5(self) -> None:
        """Black area (195mm) should match approx the outer edge of ring 5."""
        ring5 = KKG_100M.ring_diameters_mm[5]
        assert ring5 == pytest.approx(KKG_100M.black_area_diameter_mm, abs=2.0)

    def test_all_specs_have_10_rings(self) -> None:
        for key, spec in TARGET_SPECS.items():
            assert len(spec.ring_diameters_mm) == 10, f"{key} does not have 10 rings"

    def test_all_specs_ring_order(self) -> None:
        """Ring 1 must always be the largest diameter."""
        for key, spec in TARGET_SPECS.items():
            assert spec.ring_diameters_mm[1] > spec.ring_diameters_mm[10], (
                f"{key}: ring 1 should be larger than ring 10"
            )


class TestTargetSpecProperties:
    def test_black_to_total_ratio_kkp(self) -> None:
        ratio = KKP_25M.black_to_total_ratio
        assert ratio == pytest.approx(200.0 / 500.0, abs=0.01)

    def test_total_to_black_scale_kkp(self) -> None:
        scale = KKP_25M.total_to_black_scale
        assert scale == pytest.approx(2.5, abs=0.01)

    def test_total_to_black_scale_gkg(self) -> None:
        """GKG Woerden scale should be close to the old magic number 1.67."""
        scale = GKG_100M.total_to_black_scale
        assert scale == pytest.approx(1.67, abs=0.05)

    def test_ring_diameter_mm_valid(self) -> None:
        assert KKP_25M.ring_diameter_mm(10) == pytest.approx(50.0)

    def test_ring_diameter_mm_invalid(self) -> None:
        with pytest.raises(ValueError, match="Ring 0"):
            KKP_25M.ring_diameter_mm(0)

    def test_ring1_diameter_property(self) -> None:
        assert KKP_25M.ring1_diameter_mm == KKP_25M.ring_diameters_mm[1]

    def test_ring10_diameter_property(self) -> None:
        assert KKP_25M.ring10_diameter_mm == KKP_25M.ring_diameters_mm[10]


class TestTargetSpecsDict:
    def test_all_keys_present(self) -> None:
        expected = {"kkp_25m", "gkp_25m", "kkg_50m", "kkg_100m", "gkg_100m"}
        assert set(TARGET_SPECS.keys()) == expected

    def test_values_are_target_specs(self) -> None:
        for spec in TARGET_SPECS.values():
            assert isinstance(spec, TargetSpec)

    def test_names_match_keys(self) -> None:
        assert TARGET_SPECS["kkp_25m"].name == "25m KKP"
        assert TARGET_SPECS["gkp_25m"].name == "25m GKP"
        assert TARGET_SPECS["kkg_50m"].name == "50m KKG"
        assert TARGET_SPECS["kkg_100m"].name == "100m KKG"
        assert TARGET_SPECS["gkg_100m"].name == "100m GKG"
