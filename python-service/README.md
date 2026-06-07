# AimTrack Image Processing Service

Python FastAPI microservice for detecting bullet holes in target photos using OpenCV.

## Features

- Bullet hole detection using OpenCV blob detection
- Normalized coordinate output (-1 to 1 range)
- Confidence scoring for each detection
- RESTful API endpoints
- Docker support

## API Endpoints

### POST /api/v1/analyze-target
Analyze a target image and detect bullet holes.

**Request:**
- Content-Type: multipart/form-data
- File: Image file (JPG, PNG, etc.)

**Response:**
```json
{
  "success": true,
  "shots": [
    {
      "x": -0.234,
      "y": 0.456,
      "confidence": 0.892
    }
  ],
  "total_detected": 1
}
```

### GET /api/v1/health
Health check endpoint.

## Development

### Local Development

1. Install dependencies:
```bash
pip install -r requirements.txt
```

2. Run the service:
```bash
uvicorn main:app --reload
```

### Docker Development

1. Build the image:
```bash
docker build -t aimtrack-image-service .
```

2. Run the container:
```bash
docker run -p 8000:8000 aimtrack-image-service
```

## Algorithm

The service uses OpenCV for bullet hole detection:

1. Convert image to grayscale
2. Apply threshold to find dark spots
3. Remove noise with morphological operations
4. Find contours and calculate centroids
5. Normalize coordinates to -1 to 1 range
6. Calculate confidence based on circularity

## Integration with Laravel

The Laravel application communicates with this service via HTTP requests using the `AnalyzeTurnPhotoJob` queue job.
