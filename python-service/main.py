from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.middleware.cors import CORSMiddleware
import cv2
import numpy as np
from typing import List, Dict, Any
import json
import uvicorn
from PIL import Image
import io

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

def detect_bullet_holes(image_data: bytes) -> List[Dict[str, Any]]:
    """
    Detect bullet holes specifically designed for target shooting.
    Uses dark spot detection with shape filtering to find bullet holes.
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
            x_rect, y_rect, w_rect, h_rect = cv2.boundingRect(contour)
            aspect_ratio = float(w_rect) / h_rect if h_rect > 0 else 0
            
            # Bullet holes should have roughly square bounding box
            if aspect_ratio < 0.5 or aspect_ratio > 2.0:
                if idx < 15:
                    print(f"DEBUG: Contour {idx}: aspect_ratio={aspect_ratio} (rejected)")
                continue
            
            # Normalize coordinates to -1 to 1 range
            height, width = gray.shape
            x_norm = (x - width / 2) / (width / 2)
            y_norm = (y - height / 2) / (height / 2)
            
            # Debug: log the coordinates
            if idx < 15:
                print(f"DEBUG: Contour {idx} ACCEPTED: raw_x={x:.1f}, raw_y={y:.1f}, x_norm={x_norm:.3f}, y_norm={y_norm:.3f}, area={area:.1f}, circularity={circularity:.3f}")
            
            detected_shots.append({
                "x": round(x_norm, 3),
                "y": round(y_norm, 3),
                "confidence": round(circularity, 3)
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
