import os
import sys
import json
import joblib
import warnings
import numpy as np

warnings.filterwarnings('ignore')

BASE_DIR       = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH     = os.path.join(BASE_DIR, "ridge_dsi_model.pkl")
THRESHOLD_PATH = os.path.join(BASE_DIR, "risk_thresholds.pkl")

def main():
    if len(sys.argv) < 6:
        print(json.dumps({"error": "Usage: python predict.py <temp> <rh> <sunlight> <rainfall> <leafw>"}))
        sys.exit(1)

    try:
        temp     = float(sys.argv[1])
        rh       = float(sys.argv[2])
        sunlight = float(sys.argv[3])
        rainfall = float(sys.argv[4])
        leafw    = float(sys.argv[5])

        # Features order: ['Leafwetness', 'Rain-level', 'RH', 'Temp', 'Sunlight']
        features = np.array([[leafw, rainfall, rh, temp, sunlight]])

        model      = joblib.load(MODEL_PATH)
        thresholds = joblib.load(THRESHOLD_PATH)

        theta1 = float(thresholds.get("theta1", 25.97))
        theta2 = float(thresholds.get("theta2", 32.04))
        theta3 = float(thresholds.get("theta3", 60.00))

        dsi = float(model.predict(features)[0])

        if dsi <= theta1:
            risk_level = "Low"
            risk_code  = 0
        elif dsi <= theta2:
            risk_level = "Moderate"
            risk_code  = 1
        elif dsi <= theta3:
            risk_level = "High"
            risk_code  = 2
        else:
            risk_level = "Very High"
            risk_code  = 3

        result = {
            "dsi": dsi,
            "risk_level": risk_level,
            "risk_code": risk_code
        }

        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

if __name__ == "__main__":
    main()
