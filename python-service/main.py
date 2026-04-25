from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import cv2
import numpy as np
from typing import List, Dict, Any
import uvicorn

app = FastAPI(title="AimTrack Image Processing Service", version="2.0.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ─── Target detection ────────────────────────────────────────────────────────

def detect_target_area(gray: np.ndarray) -> tuple[int, int, int]:
    """
    Detect the ISSF target circle.

    Strategy (in order of reliability for typical AimTrack photos):
      1. Distance transform per enclosed dark connected component: the inscribed
         circle of the bullseye is robust against asymmetric extensions (text,
         tape, bullet-hole nicks). Components touching the image border are
         skipped so the cardboard backing doesn't win.
      2. Fall back to Hough-circle voting for partial targets where the bullseye
         is cut off.
      3. Last resort: image centre.
    """
    h, w = gray.shape
    min_dim = min(h, w)

    # ── 1. Distance transform per enclosed dark component ─────────────────
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, binary = cv2.threshold(blurred, 90, 255, cv2.THRESH_BINARY_INV)

    # Mend small gaps inside the bullseye (bullet holes punched through)
    kernel = np.ones((3, 3), np.uint8)
    binary = cv2.morphologyEx(binary, cv2.MORPH_CLOSE, kernel)

    # Find every dark connected region. The cardboard background and the
    # bullseye are both dark, but only the bullseye is fully enclosed by the
    # lighter target paper — the cardboard touches the image edges.
    n_labels, labels, stats, _ = cv2.connectedComponentsWithStats(binary, connectivity=8)

    min_area = (min_dim ** 2) * 0.005
    max_area = h * w * 0.5

    best_radius = 0.0
    best_center: tuple[int, int] | None = None

    for label_id in range(1, n_labels):
        bx, by, bw, bh, area = stats[label_id]

        if bx == 0 or by == 0 or bx + bw >= w or by + bh >= h:
            continue
        if area < min_area or area > max_area:
            continue

        mask = (labels == label_id).astype(np.uint8) * 255
        local_dist = cv2.distanceTransform(mask, cv2.DIST_L2, 5)
        _, local_max, _, local_loc = cv2.minMaxLoc(local_dist)

        if local_max > best_radius:
            best_radius = float(local_max)
            best_center = (int(local_loc[0]), int(local_loc[1]))

    min_r = min_dim * 0.05
    max_r = min_dim * 0.50

    if best_center is not None and min_r <= best_radius <= max_r:
        cx, cy = best_center
        full_r = int(best_radius / 0.60)
        print(
            f"DEBUG: Target via enclosed-blob distance transform → ({cx}, {cy}) "
            f"r_black={int(best_radius)} r_full={full_r}"
        )
        return (cx, cy, full_r)

    print(
        f"DEBUG: no enclosed dark blob with valid radius "
        f"(best={best_radius:.0f}, allowed {min_r:.0f}–{max_r:.0f}); "
        "falling back to Hough"
    )

    # ── 2. Hough fallback for partial targets ─────────────────────────────
    blurred_h = cv2.GaussianBlur(gray, (9, 9), 2)
    circles = cv2.HoughCircles(
        blurred_h,
        cv2.HOUGH_GRADIENT,
        dp=1,
        minDist=int(min_dim * 0.1),
        param1=60,
        param2=28,
        minRadius=int(min_dim * 0.05),
        maxRadius=int(min_dim * 0.65),
    )

    if circles is not None:
        circles = np.round(circles[0]).astype(int)
        issf_ratios = [0.60, 0.45, 0.30, 0.20, 0.10]
        centre_votes: list[tuple[int, int, int]] = []

        for cx, cy, r in circles:
            for ratio in issf_ratios:
                full_r = int(r / ratio)
                if full_r > min_dim:
                    continue
                centre_votes.append((cx, cy, full_r))

        if centre_votes:
            best = _best_vote_cluster(centre_votes, tolerance=int(min_dim * 0.08))
            if best:
                cx, cy, full_r = best
                print(f"DEBUG: Target via Hough voting → ({cx}, {cy}) r={full_r}")
                return (cx, cy, full_r)

    # ── 3. Last resort: image centre ──────────────────────────────────────
    cx, cy = w // 2, h // 2
    full_r = int(min_dim * 0.46)
    print(f"DEBUG: Target fallback to image centre ({cx}, {cy}) r={full_r}")
    return (cx, cy, full_r)


def _best_vote_cluster(
    votes: list[tuple[int, int, int]],
    tolerance: int,
) -> tuple[int, int, int] | None:
    if not votes:
        return None

    best_count = 0
    best_vote = votes[0]

    for v in votes:
        vx, vy, vr = v
        neighbours = [
            u for u in votes
            if abs(u[0] - vx) < tolerance and abs(u[1] - vy) < tolerance
        ]
        if len(neighbours) > best_count:
            best_count = len(neighbours)
            avg_x = int(np.mean([u[0] for u in neighbours]))
            avg_y = int(np.mean([u[1] for u in neighbours]))
            avg_r = int(np.mean([u[2] for u in neighbours]))
            best_vote = (avg_x, avg_y, avg_r)

    return best_vote


# ─── Shot (bullet hole) detection ────────────────────────────────────────────

def _is_sticker(
    gray: np.ndarray,
    cx: int,
    cy: int,
    contour: np.ndarray,
    area: float,
    circularity: float,
) -> bool:
    rect = cv2.boundingRect(contour)
    x0, y0, rw, rh = rect
    pad = max(3, int(max(rw, rh) * 0.15))
    x1 = max(0, x0 - pad)
    y1 = max(0, y0 - pad)
    x2 = min(gray.shape[1], x0 + rw + pad)
    y2 = min(gray.shape[0], y0 + rh + pad)
    region = gray[y1:y2, x1:x2]

    if region.size == 0:
        return False

    mean_intensity = float(np.mean(region))
    std_intensity = float(np.std(region))

    if mean_intensity < 55 and std_intensity < 12:
        return True
    if circularity > 0.82 and area > 400:
        return True

    return False


def _donut_score(
    gray: np.ndarray,
    cx: int,
    cy: int,
    radius: float,
) -> float:
    """
    Bullet hole signature: strong contrast between centre and surrounding ring.
      In light areas: lighter centre (light through hole) → positive score.
      In dark areas (black bullseye): darker centre, lighter ring → negative.
    Stickers are uniform → score near zero.
    """
    h, w = gray.shape
    inner_r = max(1, int(radius * 0.35))
    outer_r = max(inner_r + 2, int(radius * 0.85))

    mask_inner = np.zeros((h, w), dtype=np.uint8)
    mask_ring = np.zeros((h, w), dtype=np.uint8)

    cv2.circle(mask_inner, (cx, cy), inner_r, 255, -1)
    cv2.circle(mask_ring, (cx, cy), outer_r, 255, -1)
    cv2.circle(mask_ring, (cx, cy), inner_r, 0, -1)

    inner_pixels = gray[mask_inner == 255]
    ring_pixels = gray[mask_ring == 255]

    if inner_pixels.size == 0 or ring_pixels.size == 0:
        return 0.0

    return float(np.mean(inner_pixels)) - float(np.mean(ring_pixels))


def detect_bullet_holes(image_data: bytes) -> List[Dict[str, Any]]:
    nparr = np.frombuffer(image_data, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    if img is None:
        raise HTTPException(status_code=400, detail="Invalid image format")

    h, w = img.shape[:2]
    print(f"DEBUG: Image {w}×{h}")

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    target_cx, target_cy, target_r = detect_target_area(gray)

    image_area = h * w
    min_area = image_area * 0.000015
    max_area = image_area * 0.0005

    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    _, otsu = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    _, fixed = cv2.threshold(blurred, 80, 255, cv2.THRESH_BINARY_INV)
    combined = cv2.bitwise_and(otsu, fixed)

    kernel_open = np.ones((2, 2), np.uint8)
    kernel_close = np.ones((3, 3), np.uint8)
    combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN, kernel_open)
    combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, kernel_close)

    contours, _ = cv2.findContours(combined, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    print(f"DEBUG: {len(contours)} raw contours")

    shots: List[Dict[str, Any]] = []

    for idx, contour in enumerate(contours):
        area = cv2.contourArea(contour)
        if area < min_area or area > max_area:
            continue

        M = cv2.moments(contour)
        if M["m00"] == 0:
            continue
        cx = M["m10"] / M["m00"]
        cy = M["m01"] / M["m00"]

        perimeter = cv2.arcLength(contour, True)
        if perimeter == 0:
            continue
        circularity = 4 * np.pi * area / (perimeter ** 2)
        if circularity < 0.35:
            continue

        hull = cv2.convexHull(contour)
        hull_area = cv2.contourArea(hull)
        solidity = area / hull_area if hull_area > 0 else 0
        if solidity < 0.55:
            continue

        _, _, bw, bh = cv2.boundingRect(contour)
        aspect = bw / bh if bh > 0 else 0
        if aspect < 0.4 or aspect > 2.5:
            continue

        if _is_sticker(gray, int(cx), int(cy), contour, area, circularity):
            print(f"DEBUG: contour {idx} rejected as sticker (area={area:.0f}, circ={circularity:.2f})")
            continue

        radius_est = np.sqrt(area / np.pi)
        ds = _donut_score(gray, int(cx), int(cy), radius_est)

        if abs(ds) < 8.0:
            print(f"DEBUG: contour {idx} rejected (donut_score={ds:.1f}, |ds|<8)")
            continue

        x_norm = (cx - target_cx) / target_r
        y_norm = (cy - target_cy) / target_r

        dist = np.sqrt(x_norm ** 2 + y_norm ** 2)
        if dist > 1.30:
            print(f"DEBUG: contour {idx} rejected – outside target (dist={dist:.2f})")
            continue

        donut_norm = min(abs(ds) / 40.0, 1.0)
        confidence = round(0.4 * min(circularity / 0.9, 1.0) + 0.6 * donut_norm, 3)

        print(
            f"DEBUG: contour {idx} ACCEPTED x_norm={x_norm:.3f} y_norm={y_norm:.3f} "
            f"area={area:.0f} circ={circularity:.2f} ds={ds:.1f} conf={confidence}"
        )

        shots.append({"x": round(x_norm, 3), "y": round(y_norm, 3), "confidence": confidence})

    shots.sort(key=lambda s: s["confidence"], reverse=True)
    shots = shots[:12]
    print(f"DEBUG: Final shot count: {len(shots)}")
    return shots


# ─── Endpoints ────────────────────────────────────────────────────────────────

@app.post("/api/v1/analyze-target-v2")
async def analyze_target_v2(file: UploadFile = File(...)):
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="File must be an image")
    image_data = await file.read()
    shots = detect_bullet_holes(image_data)
    limited = shots[:10]
    return {"success": True, "shots": limited, "total_detected": len(limited)}


@app.post("/api/v1/analyze-target")
async def analyze_target(file: UploadFile = File(...)):
    if not file.content_type or not file.content_type.startswith("image/"):
        raise HTTPException(status_code=400, detail="File must be an image")
    image_data = await file.read()
    shots = detect_bullet_holes(image_data)
    return {"success": True, "shots": shots, "total_detected": len(shots)}


@app.get("/api/v1/health")
async def health_check():
    return {"status": "healthy", "service": "aimtrack-image-processor", "version": "2.0.0"}


@app.get("/")
async def root():
    return {
        "message": "AimTrack Image Processing Service",
        "version": "2.0.0",
        "endpoints": {
            "analyze_target": "/api/v1/analyze-target",
            "analyze_target_v2": "/api/v1/analyze-target-v2",
            "health": "/api/v1/health",
        },
    }


if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
