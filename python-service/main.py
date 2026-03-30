from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import cv2
import numpy as np
from typing import List, Dict, Any
import json
import uvicorn
from PIL import Image
import io
from skimage.feature import local_binary_pattern

app = FastAPI(title="AimTrack Image Processing Service", version="1.0.0")

# Enable CORS for Laravel frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure appropriately for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

class ShotDetectionResult:
    def __init__(self, x: float, y: float, confidence: float):
        self.x = x
        self.y = y
        self.confidence = confidence

def analyze_texture_features(gray_region: np.ndarray) -> float:
    """
    Analyze texture to distinguish bullet holes from stickers.
    Bullet holes have rough, irregular edges with depth shadows.
    Stickers have smooth, uniform surfaces with sharp edges.

    Returns: texture score (0-1), higher = more likely a bullet hole
    """
    if gray_region.size == 0 or gray_region.shape[0] < 3 or gray_region.shape[1] < 3:
        return 0.0

    try:
        # Local Binary Pattern for texture analysis
        # Bullet holes have higher texture variance due to torn paper edges
        lbp = local_binary_pattern(gray_region, P=8, R=1, method='uniform')
        lbp_variance = np.var(lbp)

        # Normalize variance to 0-1 range (empirically tuned)
        texture_score = min(lbp_variance / 50.0, 1.0)

        return texture_score
    except Exception as e:
        print(f"DEBUG: Texture analysis failed: {e}")
        return 0.0

def analyze_edge_characteristics(gray_region: np.ndarray) -> float:
    """
    Analyze edge sharpness to distinguish holes from stickers.
    Bullet holes have gradual, blurred edges with shadows.
    Stickers have sharp, well-defined edges.

    Returns: edge score (0-1), higher = more likely a bullet hole
    """
    if gray_region.size == 0 or gray_region.shape[0] < 5 or gray_region.shape[1] < 5:
        return 0.0

    try:
        # Apply edge detection
        edges = cv2.Canny(gray_region, 30, 100)

        # Count edge pixels
        edge_pixel_count = np.sum(edges > 0)
        total_pixels = gray_region.shape[0] * gray_region.shape[1]
        edge_density = edge_pixel_count / total_pixels

        # Bullet holes have moderate edge density (not too sharp, not too smooth)
        # Stickers have high edge density (very sharp boundaries)
        # Optimal range for bullet holes: 0.05 - 0.25
        if 0.05 <= edge_density <= 0.25:
            edge_score = 1.0
        elif edge_density < 0.05:
            # Too smooth, likely background noise
            edge_score = 0.3
        else:
            # Too sharp, likely a sticker
            edge_score = max(0.0, 1.0 - (edge_density - 0.25) * 2)

        return edge_score
    except Exception as e:
        print(f"DEBUG: Edge analysis failed: {e}")
        return 0.5

def analyze_shadow_pattern(gray_image: np.ndarray, x: int, y: int, radius: int) -> float:
    """
    Detect shadow patterns characteristic of bullet holes.
    Real holes create depth shadows with gradual intensity gradients.
    Stickers are flat and don't create natural shadows.

    Returns: shadow score (0-1), higher = more likely a bullet hole
    """
    h, w = gray_image.shape

    # Safety bounds
    x1 = max(0, int(x - radius * 1.5))
    x2 = min(w, int(x + radius * 1.5))
    y1 = max(0, int(y - radius * 1.5))
    y2 = min(h, int(y + radius * 1.5))

    if x2 - x1 < 5 or y2 - y1 < 5:
        return 0.0

    try:
        region = gray_image[y1:y2, x1:x2]

        # Calculate gradient magnitude to detect shadow transitions
        grad_x = cv2.Sobel(region, cv2.CV_64F, 1, 0, ksize=3)
        grad_y = cv2.Sobel(region, cv2.CV_64F, 0, 1, ksize=3)
        gradient_magnitude = np.sqrt(grad_x**2 + grad_y**2)

        # Bullet holes have moderate, gradual gradients (shadows)
        # Stickers have high, sharp gradients (edges)
        avg_gradient = np.mean(gradient_magnitude)

        # Check for intensity variation (depth creates shadows = darker areas)
        intensity_std = np.std(region)

        # Bullet holes have:
        # - Moderate gradient (10-40 range)
        # - Good intensity variation (std > 15)
        gradient_score = 1.0 if 10 <= avg_gradient <= 40 else 0.5
        intensity_score = min(intensity_std / 30.0, 1.0)

        shadow_score = (gradient_score * 0.6 + intensity_score * 0.4)

        return shadow_score
    except Exception as e:
        print(f"DEBUG: Shadow analysis failed: {e}")
        return 0.5

def calculate_hole_likelihood(
    gray_image: np.ndarray,
    contour: np.ndarray,
    x: int,
    y: int,
    area: float,
    circularity: float
) -> float:
    """
    Calculate the likelihood that a detected dark spot is a bullet hole
    using multiple analysis techniques.

    Returns: combined score (0-1), higher = more likely a real bullet hole
    """
    # Extract region around the contour
    rect = cv2.boundingRect(contour)
    x_rect, y_rect, w_rect, h_rect = rect

    # Add padding for context
    padding = max(5, int(max(w_rect, h_rect) * 0.3))
    x1 = max(0, x_rect - padding)
    y1 = max(0, y_rect - padding)
    x2 = min(gray_image.shape[1], x_rect + w_rect + padding)
    y2 = min(gray_image.shape[0], y_rect + h_rect + padding)

    region = gray_image[y1:y2, x1:x2]

    # Get radius estimate from area
    radius = int(np.sqrt(area / np.pi))

    # Run all analyses
    texture_score = analyze_texture_features(region)
    edge_score = analyze_edge_characteristics(region)
    shadow_score = analyze_shadow_pattern(gray_image, int(x), int(y), radius)

    # Basic shape score (from existing circularity)
    shape_score = min(circularity / 0.8, 1.0)  # Normalize circularity

    # Weighted combination
    # Texture is most important (40%), then shadow (25%), edge (20%), shape (15%)
    combined_score = (
        0.40 * texture_score +
        0.25 * shadow_score +
        0.20 * edge_score +
        0.15 * shape_score
    )

    return combined_score

def detect_target_area(gray_image: np.ndarray) -> tuple:
    """
    Detect the target area by finding the dark circular region (black target).
    Returns (center_x, center_y, radius) where radius represents the full target area.
    """
    height, width = gray_image.shape

    # First, try to find the black circle using thresholding
    # The black target should be significantly darker than the beige background
    _, binary = cv2.threshold(gray_image, 100, 255, cv2.THRESH_BINARY_INV)

    # Find contours of dark regions
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    # Find the largest circular contour (likely the black target)
    best_circle = None
    best_score = 0

    for contour in contours:
        area = cv2.contourArea(contour)

        # Must be a significant portion of the image (at least 5%)
        min_area = (min(height, width) ** 2) * 0.05
        if area < min_area:
            continue

        # Check circularity
        perimeter = cv2.arcLength(contour, True)
        if perimeter == 0:
            continue

        circularity = 4 * np.pi * area / (perimeter * perimeter)

        # Must be reasonably circular
        if circularity < 0.6:
            continue

        # Get the minimum enclosing circle
        (x, y), radius = cv2.minEnclosingCircle(contour)

        # Score based on area and circularity
        score = area * circularity

        if score > best_score:
            best_score = score
            best_circle = (int(x), int(y), int(radius))

    if best_circle:
        center_x, center_y, radius = best_circle

        # The detected circle is the black target (rings 7-10)
        # We need to scale up to include the beige rings (1-6)
        # Based on ISSF target specifications:
        # - Rings 7-10 (black area) occupy ~60% of total radius
        # - Rings 1-6 (beige area) are the outer 40%
        # Therefore: black_radius / full_radius ≈ 0.6
        # So multiply radius by (1 / 0.6) ≈ 1.67
        full_radius = int(radius * 1.67)

        print(f"DEBUG: Black target detected at ({center_x}, {center_y}) with radius {radius}")
        print(f"DEBUG: Scaled to full target radius: {full_radius} (scale factor: 1.67)")
        print(f"DEBUG: Image size: {width}x{height}, full radius is {full_radius/min(width,height)*100:.1f}% of image")

        return (center_x, center_y, full_radius)

    # Fallback: try Hough Circle detection
    edges = cv2.Canny(gray_image, 30, 100)
    circles = cv2.HoughCircles(
        edges,
        cv2.HOUGH_GRADIENT,
        dp=1,
        minDist=height // 2,
        param1=50,
        param2=30,
        minRadius=int(min(height, width) * 0.2),
        maxRadius=int(min(height, width) * 0.7)
    )

    if circles is not None and len(circles[0]) > 0:
        # Take the largest circle
        largest = max(circles[0], key=lambda c: c[2])
        center_x, center_y, radius = int(largest[0]), int(largest[1]), int(largest[2])
        print(f"DEBUG: Hough circle detected at ({center_x}, {center_y}) with radius {radius}")
        return (center_x, center_y, radius)

    # Last resort: use image center
    center_x, center_y = width // 2, height // 2
    radius = int(min(height, width) * 0.46)
    print(f"DEBUG: No target detected, using image center ({center_x}, {center_y}) with radius {radius}")
    return (center_x, center_y, radius)

def detect_bullet_holes(image_data: bytes) -> List[Dict[str, Any]]:
    """
    Detect bullet holes specifically designed for target shooting.
    Uses dark spot detection with shape filtering to find bullet holes.
    Coordinates are normalized relative to the detected target circle.
    """
    print("DEBUG: detect_bullet_holes function called!")

    try:
        # Convert bytes to numpy array
        nparr = np.frombuffer(image_data, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)

        if img is None:
            raise HTTPException(status_code=400, detail="Invalid image format")

        print(f"DEBUG: Image shape: {img.shape}")
        
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        # Detect the target area first
        target_center_x, target_center_y, target_radius = detect_target_area(gray)

        # Apply moderate blur to reduce noise but keep bullet hole details
        blurred = cv2.GaussianBlur(gray, (5, 5), 0)
        
        # Use Otsu's thresholding for automatic threshold selection
        _, thresh = cv2.threshold(blurred, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
        
        # Additional high threshold to catch only very dark spots
        _, dark_thresh = cv2.threshold(blurred, 70, 255, cv2.THRESH_BINARY_INV)
        
        # Combine both thresholds
        combined = cv2.bitwise_and(thresh, dark_thresh)
        
        # Remove small noise but keep bullet holes
        kernel = np.ones((2,2), np.uint8)
        combined = cv2.morphologyEx(combined, cv2.MORPH_OPEN, kernel)
        
        # Fill small gaps in bullet holes
        kernel = np.ones((3,3), np.uint8)
        combined = cv2.morphologyEx(combined, cv2.MORPH_CLOSE, kernel)
        
        # Find contours
        contours, _ = cv2.findContours(combined, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        
        print(f"DEBUG: Found {len(contours)} contours with bullet hole detection")
        
        detected_shots = []

        for idx, contour in enumerate(contours):
            area = cv2.contourArea(contour)

            # Bullet holes are typically 15-300 pixels
            if area < 15 or area > 300:
                if idx < 15:
                    print(f"DEBUG: Contour {idx}: area={area} (rejected - out of range)")
                continue

            # Get center point
            M = cv2.moments(contour)
            if M["m00"] == 0:
                continue

            x = M["m10"] / M["m00"]
            y = M["m01"] / M["m00"]

            # Circularity check - bullet holes should be reasonably circular
            perimeter = cv2.arcLength(contour, True)
            if perimeter == 0:
                continue

            circularity = 4 * np.pi * area / (perimeter * perimeter)

            # Bullet holes should have decent circularity
            if circularity < 0.4:
                if idx < 15:
                    print(f"DEBUG: Contour {idx}: circularity={circularity} (rejected)")
                continue

            # Solidity check - bullet holes should be solid
            hull = cv2.convexHull(contour)
            hull_area = cv2.contourArea(hull)
            solidity = float(area) / hull_area if hull_area > 0 else 0

            if solidity < 0.6:
                if idx < 15:
                    print(f"DEBUG: Contour {idx}: solidity={solidity} (rejected)")
                continue

            # Additional check: aspect ratio of bounding box
            _, _, w_rect, h_rect = cv2.boundingRect(contour)
            aspect_ratio = float(w_rect) / h_rect if h_rect > 0 else 0

            # Bullet holes should have roughly square bounding box
            if aspect_ratio < 0.5 or aspect_ratio > 2.0:
                if idx < 15:
                    print(f"DEBUG: Contour {idx}: aspect_ratio={aspect_ratio} (rejected)")
                continue

            # NEW: Calculate likelihood that this is a real bullet hole (not a sticker)
            hole_likelihood = calculate_hole_likelihood(
                gray_image=gray,
                contour=contour,
                x=int(x),
                y=int(y),
                area=area,
                circularity=circularity
            )

            # Require minimum likelihood threshold to filter out stickers
            LIKELIHOOD_THRESHOLD = 0.35  # Tunable: higher = stricter filtering
            if hole_likelihood < LIKELIHOOD_THRESHOLD:
                if idx < 15:
                    print(f"DEBUG: Contour {idx}: hole_likelihood={hole_likelihood:.3f} (rejected - likely sticker)")
                continue

            # Normalize coordinates to -1 to 1 range relative to the detected target circle
            # This ensures shots are positioned correctly even if the photo includes area outside the target
            x_norm = (x - target_center_x) / target_radius
            y_norm = (y - target_center_y) / target_radius

            # Debug: log the coordinates and distances
            distance_from_center = np.sqrt(x_norm**2 + y_norm**2)
            if idx < 15:
                print(f"DEBUG: Contour {idx} ACCEPTED: raw_x={x:.1f}, raw_y={y:.1f}, center=({target_center_x}, {target_center_y}), radius={target_radius}, x_norm={x_norm:.3f}, y_norm={y_norm:.3f}, dist={distance_from_center:.3f}, area={area:.1f}, circularity={circularity:.3f}, hole_likelihood={hole_likelihood:.3f}")

            detected_shots.append({
                "x": round(x_norm, 3),
                "y": round(y_norm, 3),
                "confidence": round(hole_likelihood, 3)  # Use hole_likelihood instead of circularity
            })

        # Limit to maximum 12 shots (reasonable for a target)
        detected_shots = detected_shots[:12]
        
        print(f"DEBUG: Final detected shots count: {len(detected_shots)}")
        
        # Sort by confidence (highest first)
        detected_shots.sort(key=lambda x: x["confidence"], reverse=True)
        
        return detected_shots
        
    except Exception as e:
        print(f"DEBUG: Exception in detect_bullet_holes: {e}")
        raise HTTPException(status_code=500, detail=f"Image processing failed: {str(e)}")

@app.post("/api/v1/analyze-target-v2")
async def analyze_target_v2(file: UploadFile = File(...)):
    """
    Analyze a target image and detect bullet holes with conservative limits.
    Returns maximum 10 shots to prevent overwhelming the system.

    Args:
        file: Image file to analyze

    Returns:
        JSON response with detected shots (max 10)
    """
    print("DEBUG: V2 endpoint called!")
    print(f"DEBUG: File name: {file.filename}, content type: {file.content_type}")

    # Validate file type
    if not file.content_type or not file.content_type.startswith('image/'):
        raise HTTPException(status_code=400, detail="File must be an image")

    # Read file content
    image_data = await file.read()
    print(f"DEBUG: Image data size: {len(image_data)} bytes")

    # Detect bullet holes
    detected_shots = detect_bullet_holes(image_data)

    print(f"DEBUG: Detected {len(detected_shots)} shots before limiting")

    # Limit to maximum 10 shots for safety
    limited_shots = detected_shots[:10]

    print(f"DEBUG: Returning {len(limited_shots)} shots")

    return {
        "success": True,
        "shots": limited_shots,
        "total_detected": len(limited_shots)
    }

@app.post("/api/v1/analyze-target")
async def analyze_target(file: UploadFile = File(...)):
    """
    Analyze a target image and detect bullet holes.
    
    Args:
        file: Image file to analyze
        
    Returns:
        JSON response with detected shots
    """
    print("DEBUG: Starting analysis...")
    
    # Validate file type
    if not file.content_type or not file.content_type.startswith('image/'):
        raise HTTPException(status_code=400, detail="File must be an image")
    
    # Read file content
    image_data = await file.read()
    
    print(f"DEBUG: Image data size: {len(image_data)} bytes")
    
    # Detect bullet holes
    detected_shots = detect_bullet_holes(image_data)
    
    print(f"DEBUG: Detected {len(detected_shots)} shots before filtering")
    
    # Log detected shots for debugging
    print(f"Detected {len(detected_shots)} shots:")
    for i, shot in enumerate(detected_shots[:10]):  # Log first 10 shots
        print(f"  Shot {i+1}: x={shot['x']}, y={shot['y']}, confidence={shot['confidence']}")
    if len(detected_shots) > 10:
        print(f"  ... and {len(detected_shots) - 10} more shots")
    
    # Also save to a debug file
    with open("/tmp/debug_shots.json", "w") as f:
        import json
        json.dump({
            "total_detected": len(detected_shots),
            "shots": detected_shots[:20]  # Save first 20 for analysis
        }, f, indent=2)
    
    return {
        "success": True,
        "shots": detected_shots,
        "total_detected": len(detected_shots)
    }

@app.get("/api/v1/health")
async def health_check():
    """Health check endpoint"""
    return {"status": "healthy", "service": "aimtrack-image-processor"}

@app.get("/api/v1/debug")
async def debug_info():
    """Debug endpoint to show latest detection results"""
    try:
        with open("/tmp/debug_shots.json", "r") as f:
            import json
            data = json.load(f)
        return data
    except:
        return {"error": "No debug data available"}

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "message": "AimTrack Image Processing Service",
        "version": "1.0.0",
        "endpoints": {
            "analyze_target": "/api/v1/analyze-target",
            "health": "/api/v1/health"
        }
    }

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)
