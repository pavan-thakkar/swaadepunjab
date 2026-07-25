import { initializeApp, getApps, getApp } from "firebase/app";
import { getAuth } from "firebase/auth";

const firebaseConfig = {
  apiKey: "AIzaSyBSV_slCr9YUXMuZiXqyXeOHd8gFSU_5Sw",
  authDomain: "swaad-e-punjab-b7e9a.firebaseapp.com",
  projectId: "swaad-e-punjab-b7e9a",
  storageBucket: "swaad-e-punjab-b7e9a.firebasestorage.app",
  messagingSenderId: "82292707740",
  appId: "1:82292707740:web:0444cf4a30f2c611eef859",
  measurementId: "G-3M22NZ7HZH"
};

// Initialize Firebase (and avoid duplicate app initialization during Next.js Hot Reloads)
const app = getApps().length === 0 ? initializeApp(firebaseConfig) : getApp();
const auth = getAuth(app);

export { app, auth };
