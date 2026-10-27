/* @vitest-environment jsdom */
import { afterEach, describe, expect, it, vi } from "vitest";
import { cleanup, render, screen } from "@testing-library/react";
import { MemoryRouter, Route, Routes } from "react-router-dom";

const state = vi.hoisted(() => ({ auth: {} }));
vi.mock("../features/auth/context/AuthContext", () => ({ useAuth: () => state.auth }));
import ProtectedRoute, { GuestRoute, PlatformRoute } from "./ProtectedRoute";

afterEach(cleanup);
const draw = (guard, path = "/private") => render(<MemoryRouter initialEntries={[path]}><Routes><Route element={guard}><Route path={path} element={<p>allowed</p>} /></Route><Route path="/login" element={<p>login</p>} /><Route path="/dashboard" element={<p>dashboard</p>} /><Route path="/platform/dashboard" element={<p>platform</p>} /></Routes></MemoryRouter>);

describe("route authorization", () => {
  it("sends anonymous users to login", () => { state.auth = { loading: false, isAuthenticated: false, can: () => false }; draw(<ProtectedRoute />); expect(screen.getByText("login")).toBeTruthy(); });
  it("blocks missing company permissions", () => { state.auth = { loading: false, isAuthenticated: true, user: {}, can: () => false }; draw(<ProtectedRoute permission="users.view" />); expect(screen.getByText("dashboard")).toBeTruthy(); });
  it("allows an authorized company user", () => { state.auth = { loading: false, isAuthenticated: true, user: {}, can: () => true }; draw(<ProtectedRoute permission="users.view" />); expect(screen.getByText("allowed")).toBeTruthy(); });
  it("keeps company users out of platform routes", () => { state.auth = { loading: false, isAuthenticated: true, user: {}, can: () => true }; draw(<PlatformRoute />); expect(screen.getByText("dashboard")).toBeTruthy(); });
  it("allows only platform identity through the platform guard", () => { state.auth = { loading: false, isAuthenticated: true, user: { is_platform_admin: true }, can: () => false }; draw(<PlatformRoute />); expect(screen.getByText("allowed")).toBeTruthy(); });
  it("redirects authenticated users away from guest routes", () => { state.auth = { loading: false, isAuthenticated: true, user: { is_platform_admin: true } }; draw(<GuestRoute />); expect(screen.getByText("platform")).toBeTruthy(); });
});
