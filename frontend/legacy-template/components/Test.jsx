import { useEffect, useState } from "react";
import api from "../api/axios";

function Tests() {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    api
      .get("/projects")
      .then((response) => {
        console.log("Laravel API Response:", response.data);
        setProjects(response.data);
      })
      .catch((error) => {
        console.error("API Error:", error);
        setError("Failed to load projects");
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) return <p>Loading projects...</p>;

  if (error) return <p>{error}</p>;

  return (
    <div>
      <h1>Construction Projects</h1>

      {projects.map((project) => (
        <div key={project.id}>
          <h3>{project.name}</h3>
          <p>Status: {project.status}</p>
        </div>
      ))}
    </div>
  );
}

export default Tests;
