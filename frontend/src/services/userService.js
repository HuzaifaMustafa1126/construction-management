import api from "../api/axios";

export const createUser = async (userData) => {
  // Do not leave the form in a permanent submitting state if the API server
  // accepts the connection but never completes the response.
  const response = await api.post("/users", userData, { timeout: 15000 });
  return response.data;
};

export const getUsers = async () => {
  const response = await api.get("/users");
  const users = response.data.users;

  if (Array.isArray(users)) return users;

  // Laravel may paginate this endpoint. Fetch every remaining page so the
  // client-side filters and pagination operate on the complete user list.
  const firstPage = users?.data || [];
  const lastPage = Number(users?.last_page || 1);
  if (lastPage <= 1) return firstPage;

  const remainingPages = await Promise.all(
    Array.from({ length: lastPage - 1 }, (_, index) =>
      api.get("/users", { params: { page: index + 2 } }),
    ),
  );

  return remainingPages.reduce(
    (allUsers, pageResponse) => {
      const pageUsers = pageResponse.data.users;
      return allUsers.concat(
        Array.isArray(pageUsers) ? pageUsers : pageUsers?.data || [],
      );
    },
    firstPage,
  );
};
export const deleteUser = async (userId) => {
  const response = await api.delete(`/users/${userId}`);
  return response.data;
};
