import api from "../api/axios";

export const login = async (email, password) => {
  const response = await api.post("/login", {
    email,
    password,
  });

  if (!response.data.token || !response.data.user) {
    throw new Error("The login response is missing the user or token.");
  }

  sessionStorage.setItem("auth_token", response.data.token);

  return response.data;
};

export const getCurrentUser = async () => {
  const response = await api.get("/user");
  return response.data.user || response.data;
};

export const logout = async () => {
  try {
    await api.post("/logout");
  } finally {
    sessionStorage.removeItem("auth_token");
  }
};
